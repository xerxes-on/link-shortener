<?php

namespace App\Filament\Pages;

use App\Models\IntegrationSetting;
use App\Services\GoogleAnalyticsService;
use Filament\Actions;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class IntegrationsSettings extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-puzzle-piece';

    protected string $view = 'filament.pages.integrations-settings';

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'Integrations';

    public static function canAccess(): bool
    {
        return auth()->user()->can('page_IntegrationsSettings');
    }

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'google_analytics_enabled' => IntegrationSetting::get('google_analytics', 'enabled', false),
            'google_analytics_measurement_id' => IntegrationSetting::get('google_analytics', 'measurement_id', ''),
            'google_analytics_api_secret' => IntegrationSetting::get('google_analytics', 'api_secret', ''),
            'google_analytics_notes' => IntegrationSetting::get('google_analytics', 'notes', ''),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Google Analytics 4')
                    ->description('Integrate with Google Analytics 4 to send click events server-side. This provides backup analytics and can improve performance by reducing local database writes. Note: Connection testing may not work in local development environments.')
                    ->icon('heroicon-o-chart-bar')
                    ->schema([
                        Toggle::make('google_analytics_enabled')
                            ->label('Enable Google Analytics Integration')
                            ->helperText('When enabled, click events will be sent to Google Analytics in addition to local tracking.')
                            ->live(),

                        TextInput::make('google_analytics_measurement_id')
                            ->label('Measurement ID')
                            ->helperText('Found in GA4 Admin → Data Streams → Your Stream (e.g., G-XXXXXXXXXX)')
                            ->placeholder('G-XXXXXXXXXX')
                            ->required(fn ($get) => $get('google_analytics_enabled'))
                            ->visible(fn ($get) => $get('google_analytics_enabled')),

                        TextInput::make('google_analytics_api_secret')
                            ->label('Measurement Protocol API Secret')
                            ->helperText('Create in GA4 Admin → Data Streams → Your Stream → Measurement Protocol API secrets')
                            ->password()
                            ->revealable()
                            ->required(fn ($get) => $get('google_analytics_enabled'))
                            ->visible(fn ($get) => $get('google_analytics_enabled')),

                        Textarea::make('google_analytics_notes')
                            ->label('Notes')
                            ->helperText('Optional notes about this integration setup')
                            ->rows(3)
                            ->visible(fn ($get) => $get('google_analytics_enabled')),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        // Check permission for updating settings
        if (! auth()->user()->can('page_IntegrationsSettings')) {
            Notification::make()
                ->title('Access denied')
                ->body('You do not have permission to update integration settings.')
                ->danger()
                ->send();

            return;
        }

        $data = $this->form->getState();

        // Save Google Analytics settings
        IntegrationSetting::set('google_analytics', 'enabled', $data['google_analytics_enabled']);

        if ($data['google_analytics_enabled']) {
            IntegrationSetting::set('google_analytics', 'measurement_id', $data['google_analytics_measurement_id']);
            IntegrationSetting::set('google_analytics', 'api_secret', $data['google_analytics_api_secret']);
            IntegrationSetting::set('google_analytics', 'notes', $data['google_analytics_notes'] ?? '');
        } else {
            // Deactivate all GA settings when disabled
            IntegrationSetting::where('provider', 'google_analytics')->update(['is_active' => false]);
        }

        // Clear GA caches when settings are updated
        GoogleAnalyticsService::clearCache();

        Notification::make()
            ->title('Integration settings saved')
            ->success()
            ->send();
    }

    public function testGoogleAnalytics(): void
    {
        $data = $this->form->getState();

        // Temporarily save test settings to use current form values
        if (! $data['google_analytics_enabled'] || empty($data['google_analytics_measurement_id']) || empty($data['google_analytics_api_secret'])) {
            Notification::make()
                ->title('Missing configuration')
                ->body('Please enable Google Analytics and provide both Measurement ID and API Secret before testing.')
                ->warning()
                ->send();

            return;
        }

        // Temporarily set form values for testing (without persisting)
        $originalEnabled = IntegrationSetting::get('google_analytics', 'enabled');
        $originalMeasurementId = IntegrationSetting::get('google_analytics', 'measurement_id');
        $originalApiSecret = IntegrationSetting::get('google_analytics', 'api_secret');

        IntegrationSetting::set('google_analytics', 'enabled', $data['google_analytics_enabled']);
        IntegrationSetting::set('google_analytics', 'measurement_id', $data['google_analytics_measurement_id']);
        IntegrationSetting::set('google_analytics', 'api_secret', $data['google_analytics_api_secret']);

        try {
            $gaService = new GoogleAnalyticsService;
            $result = $gaService->testConnection();
        } finally {
            // Restore original values
            if ($originalEnabled !== null) {
                IntegrationSetting::set('google_analytics', 'enabled', $originalEnabled);
            }
            if ($originalMeasurementId !== null) {
                IntegrationSetting::set('google_analytics', 'measurement_id', $originalMeasurementId);
            }
            if ($originalApiSecret !== null) {
                IntegrationSetting::set('google_analytics', 'api_secret', $originalApiSecret);
            }
        }

        if ($result['success']) {
            Notification::make()
                ->title('Connection successful')
                ->body($result['message'])
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Connection failed')
                ->body($result['message'])
                ->danger()
                ->send();
        }
    }

    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('save')
                ->label('Save Settings')
                ->submit('save'),

            Actions\Action::make('test_ga')
                ->label('Test Google Analytics')
                ->action('testGoogleAnalytics')
                ->color('info')
                ->icon('heroicon-o-signal')
                ->visible(fn () => $this->data['google_analytics_enabled'] ?? false),
        ];
    }
}
