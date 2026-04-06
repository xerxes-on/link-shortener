<?php

namespace App\Filament\Resources\LinkResource\Pages;

use App\Filament\Resources\LinkResource;
use App\Models\Link;
use App\Models\LinkGroup;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;

class ListLinks extends ListRecords implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = LinkResource::class;

    public ?array $quickAddData = [];

    public function mount(): void
    {
        parent::mount();

        // Initialize with default group if available
        $defaultGroup = LinkGroup::where('is_default', true)->first();
        $this->quickAddForm->fill([
            'group_id' => $defaultGroup?->id,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getForms(): array
    {
        return [
            'quickAddForm',
        ];
    }

    public function quickAddForm(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Schemas\Components\Section::make('Quick Add Link')
                    ->schema([
                        Schemas\Components\Grid::make(['default' => 1, 'md' => 4])
                            ->schema([
                                Forms\Components\TextInput::make('custom_slug')
                                    ->label('Short URL')
                                    ->placeholder('custom-url (optional)')
                                    ->regex('/^[a-z0-9\-_]*$/')
                                    ->unique(table: Link::class, column: 'custom_slug', ignoreRecord: true)
                                    ->unique(table: Link::class, column: 'short_code', ignoreRecord: true),
                                Forms\Components\TextInput::make('original_url')
                                    ->label('Destination URL')
                                    ->required()
                                    ->url()
                                    ->placeholder('https://example.com/your-long-url'),
                                Forms\Components\Select::make('group_id')
                                    ->label('Group')
                                    ->options(LinkGroup::pluck('name', 'id'))
                                    ->searchable()
                                    ->placeholder('Select group...'),
                                Schemas\Components\Actions::make([
                                    Actions\Action::make('quickAdd')
                                        ->label('Add Link')
                                        ->icon('heroicon-m-plus')
                                        ->color('success')
                                        ->action('quickAddLink')
                                        ->keyBindings(['mod+s', 'enter']),
                                ])
                                    ->alignEnd()
                                    ->extraAttributes(['style' => 'padding-top: 1.75rem;']),
                            ]),
                    ])
                    ->compact(),
            ])
            ->statePath('quickAddData')
            ->model(Link::class);
    }

    public function quickAddLink(): void
    {
        $data = $this->quickAddForm->getState();

        if (empty($data['original_url'])) {
            Notification::make()
                ->title('Destination URL is required')
                ->danger()
                ->send();

            return;
        }

        // Validate custom slug if provided
        if (! empty($data['custom_slug'])) {
            if (! preg_match('/^[a-z0-9\-_]*$/', $data['custom_slug'])) {
                Notification::make()
                    ->title('Short URL can only contain letters, numbers, hyphens, and underscores')
                    ->danger()
                    ->send();

                return;
            }

            if (Link::where('custom_slug', $data['custom_slug'])->exists() ||
                Link::where('short_code', $data['custom_slug'])->exists()) {
                Notification::make()
                    ->title('Short URL already taken')
                    ->danger()
                    ->send();

                return;
            }
        }

        // Create the link
        $linkData = [
            'original_url' => $data['original_url'],
            'custom_slug' => $data['custom_slug'] ?: null,
            'group_id' => $data['group_id'] ?: null,
            'redirect_type' => 302,
            'is_active' => true,
            'created_by' => auth()->id(),
        ];

        // Generate short code if no custom slug provided
        if (empty($linkData['custom_slug'])) {
            do {
                $shortCode = Str::random(8);
            } while (Link::where('short_code', $shortCode)->exists());

            $linkData['short_code'] = $shortCode;
        } else {
            $linkData['short_code'] = $linkData['custom_slug'];
        }

        $link = Link::create($linkData);

        // Keep the selected group for next time
        $selectedGroupId = $data['group_id'];

        // Reset form but keep group
        $this->quickAddForm->fill([
            'custom_slug' => '',
            'original_url' => '',
            'group_id' => $selectedGroupId,
        ]);

        Notification::make()
            ->title('Link created!')
            ->body(url($link->short_code))
            ->success()
            ->duration(4000)
            ->send();

        // Refresh the table to show new link
        $this->resetTable();

        // Focus on the short URL field for next entry
        $this->dispatch('focusShortUrl');
    }

    public function getHeader(): ?View
    {
        return view('filament.pages.quick-add-header', [
            'form' => $this->quickAddForm,
            'headerActions' => $this->getCachedHeaderActions(),
        ]);
    }
}
