<?php

namespace App\Filament\Pages;

use App\Jobs\ProcessCsvImportJob;
use App\Models\User;
use App\Services\CsvImportService;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CsvImport extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected string $view = 'filament.pages.csv-import';

    protected static \UnitEnum|string|null $navigationGroup = 'System';

    protected static ?string $title = 'CSV Import';

    protected static ?int $navigationSort = 5;

    public ?array $data = [];

    public function mount(): void
    {
        // Check if user has permission to access CSV import page
        if (! auth()->user()->can('page_CsvImport')) {
            abort(403, 'You do not have permission to access CSV import.');
        }

        // Also check if user can create links (required for importing)
        if (! auth()->user()->can('create_link')) {
            abort(403, 'You do not have permission to create links.');
        }
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->can('page_CsvImport') && $user?->can('create_link') ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('CSV Import')
                    ->description('Import multiple links from a CSV file. Use the template below to format your data correctly.')
                    ->schema([
                        FileUpload::make('csv_file')
                            ->label('CSV File')
                            ->acceptedFileTypes(['text/csv', 'application/csv', 'text/plain', '.csv'])
                            ->maxSize(10240) // 10MB
                            ->required()
                            ->multiple(false) // Ensure single file only
                            ->helperText('Maximum file size: 10MB. Upload your edited CSV file (not the template).')
                            ->disk('local')
                            ->directory('csv-imports')
                            ->visibility('private')
                            ->preserveFilenames() // Keep original filename
                            ->moveFiles() // Move files instead of copying
                            ->uploadingMessage('Uploading CSV file...')
                            ->removeUploadedFileButtonPosition('right')
                            ->uploadProgressIndicatorPosition('left'),
                    ]),
            ])
            ->statePath('data');
    }

    public function downloadTemplate()
    {
        $csvContent = CsvImportService::getSampleCsv();

        return response()->streamDownload(function () use ($csvContent) {
            echo $csvContent;
        }, 'link_import_template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function importCsv(): void
    {
        $formData = $this->form->getState();

        // Try to get file from form data first, then component data
        $csvFile = $formData['csv_file'] ?? $this->data['csv_file'] ?? null;

        if (empty($csvFile)) {
            Notification::make()
                ->title('No file selected')
                ->body('Please select a CSV file to import.')
                ->warning()
                ->send();

            return;
        }

        // Handle file upload - Filament returns TemporaryUploadedFile objects
        $csvContent = null;
        $fileName = null;

        if (is_array($csvFile)) {
            if (empty($csvFile) || ! isset($csvFile[0])) {
                Notification::make()
                    ->title('No file selected')
                    ->body('Please select a CSV file to import.')
                    ->warning()
                    ->send();

                return;
            }
            $uploadedFile = $csvFile[0];
        } else {
            $uploadedFile = $csvFile;
        }

        // Handle TemporaryUploadedFile object or file path string
        if (is_object($uploadedFile) && method_exists($uploadedFile, 'getRealPath')) {
            // This is a TemporaryUploadedFile object
            try {
                $csvContent = file_get_contents($uploadedFile->getRealPath());
                $fileName = $uploadedFile->getClientOriginalName();
            } catch (\Exception $e) {
                Notification::make()
                    ->title('File Error')
                    ->body('Unable to read the uploaded file: '.$e->getMessage())
                    ->danger()
                    ->send();

                return;
            }
        } elseif (is_string($uploadedFile)) {
            // This is a stored file path
            if (! Storage::disk('local')->exists($uploadedFile)) {
                Notification::make()
                    ->title('File not found')
                    ->body('The uploaded file could not be found. Please try uploading again.')
                    ->danger()
                    ->send();
                $this->data['csv_file'] = null;
                $this->form->fill(['csv_file' => null]);

                return;
            }

            try {
                $csvContent = Storage::disk('local')->get($uploadedFile);
                $fileName = basename($uploadedFile);
            } catch (\Exception $e) {
                Notification::make()
                    ->title('File Error')
                    ->body('Unable to read the uploaded file: '.$e->getMessage())
                    ->danger()
                    ->send();

                return;
            }
        } else {
            Notification::make()
                ->title('Invalid file')
                ->body('Invalid file format received. Please try uploading the file again.')
                ->danger()
                ->send();
            $this->data['csv_file'] = null;

            return;
        }

        if (empty($csvContent)) {
            Notification::make()
                ->title('Empty file')
                ->body('The uploaded file is empty. Please check your CSV file.')
                ->warning()
                ->send();

            return;
        }

        try {

            $csvService = new CsvImportService;
            $user = auth()->user();

            // Quick validation check first
            $parseResult = $csvService->parseAndValidate($csvContent, $user);

            if (! $parseResult['success']) {
                $errorMessage = "CSV validation failed:\n\n".implode("\n", $parseResult['errors']);

                // Add warnings if any
                if (! empty($parseResult['warnings'])) {
                    $errorMessage .= "\n\nWarnings (will be skipped):\n".implode("\n", $parseResult['warnings']);
                }

                // Clean up uploaded file (only if it's a stored file)
                if (is_string($uploadedFile) && Storage::disk('local')->exists($uploadedFile)) {
                    Storage::disk('local')->delete($uploadedFile);
                }
                $this->data['csv_file'] = null;
                $this->form->fill(['csv_file' => null]);

                Notification::make()
                    ->title('CSV Validation Failed')
                    ->body($errorMessage)
                    ->danger()
                    ->persistent()
                    ->send();

                return;
            }

            // Check if this is a large import (>100 rows)
            $totalRows = $parseResult['total_rows'];

            if ($totalRows > 100) {
                // For large files, we need to store the content temporarily
                $tempFile = 'csv-imports/temp_'.Str::uuid().'.csv';
                Storage::disk('local')->put($tempFile, $csvContent);

                // Process in background
                $sessionId = Str::uuid();
                ProcessCsvImportJob::dispatch($tempFile, $user->id, $sessionId);

                $this->data['csv_file'] = null;
                $this->form->fill(['csv_file' => null]);

                Notification::make()
                    ->title('Import Started')
                    ->body("Your CSV file contains {$totalRows} rows and will be processed in the background. You'll receive a notification when it's complete.")
                    ->info()
                    ->persistent()
                    ->send();

                // Redirect to links page
                $this->redirect(route('filament.admin.resources.links.index'));

                return;
            }

            // Process immediately for small files
            $importResult = $csvService->import($parseResult['data'], $user);

            // Clean up uploaded file (only if it's a stored file)
            if (is_string($uploadedFile) && Storage::disk('local')->exists($uploadedFile)) {
                Storage::disk('local')->delete($uploadedFile);
            }
            $this->data['csv_file'] = null;

            if ($importResult['success']) {
                $successMessage = "Successfully imported {$importResult['imported']} out of {$importResult['total']} links.";

                // Add warnings about skipped rows
                if (! empty($parseResult['warnings'])) {
                    $successMessage .= "\n\nSkipped {$parseResult['skipped_rows']} duplicate links:\n".implode("\n", $parseResult['warnings']);
                }

                if (! empty($importResult['errors'])) {
                    $successMessage .= "\n\nSome links failed to import:\n".implode("\n", $importResult['errors']);
                }

                Notification::make()
                    ->title('Import Completed')
                    ->body($successMessage)
                    ->success()
                    ->persistent()
                    ->send();

                // Redirect to links page to see imported links
                $this->redirect(route('filament.admin.resources.links.index'));
            } else {
                Notification::make()
                    ->title('Import Failed')
                    ->body('No links were imported. Please check your CSV file and try again.')
                    ->danger()
                    ->send();
            }

        } catch (\Exception $e) {
            // Clean up uploaded file on error (only if it's a stored file)
            if (is_string($uploadedFile) && Storage::disk('local')->exists($uploadedFile)) {
                Storage::disk('local')->delete($uploadedFile);
            }
            $this->data['csv_file'] = null;

            Notification::make()
                ->title('Import Error')
                ->body('An error occurred while processing your CSV file: '.$e->getMessage())
                ->danger()
                ->send();
        }
    }
}
