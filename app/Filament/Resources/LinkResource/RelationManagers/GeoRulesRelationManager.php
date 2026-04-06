<?php

namespace App\Filament\Resources\LinkResource\RelationManagers;

use App\Services\GeolocationService;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class GeoRulesRelationManager extends RelationManager
{
    protected static string $relationship = 'geoRules';

    protected static ?string $title = 'Geo-Targeting Rules';

    protected static \BackedEnum|string|null $icon = 'heroicon-o-globe-alt';

    public function form(Schema $schema): Schema
    {
        $geolocationService = app(GeolocationService::class);

        return $schema
            ->schema([
                Forms\Components\Select::make('match_type')
                    ->label('Match Type')
                    ->options([
                        'country' => 'Countries',
                        'continent' => 'Continents',
                        'region' => 'Custom Regions',
                    ])
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(fn ($state, Schemas\Components\Utilities\Set $set) => $set('match_values', [])),

                Forms\Components\Select::make('match_values')
                    ->label('Match Values')
                    ->multiple()
                    ->required()
                    ->searchable()
                    ->options(function (Schemas\Components\Utilities\Get $get) {
                        return match ($get('match_type')) {
                            'country' => $this->getCountryOptions(),
                            'continent' => $this->getContinentOptions(),
                            'region' => $this->getRegionOptions(),
                            default => []
                        };
                    })
                    ->helperText(function (Schemas\Components\Utilities\Get $get) {
                        return match ($get('match_type')) {
                            'country' => 'Select one or more countries',
                            'continent' => 'Select one or more continents',
                            'region' => 'Select predefined regions (e.g., GDPR Zone, North America)',
                            default => 'Select a match type first'
                        };
                    })
                    ->visible(fn (Schemas\Components\Utilities\Get $get) => $get('match_type') !== null),

                Forms\Components\TextInput::make('redirect_url')
                    ->label('Redirect URL')
                    ->url()
                    ->required()
                    ->maxLength(255)
                    ->helperText('URL to redirect matching visitors to'),

                Forms\Components\TextInput::make('priority')
                    ->label('Priority')
                    ->numeric()
                    ->default(0)
                    ->helperText('Lower numbers have higher priority'),

                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),

                Forms\Components\Placeholder::make('geo_availability')
                    ->label('Geolocation Status')
                    ->content($geolocationService->isAvailable()
                        ? 'MaxMind database is available'
                        : 'MaxMind database not found. Run: php artisan geoip:update')
                    ->visible(! $geolocationService->isAvailable()),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('redirect_url')
            ->columns([
                Tables\Columns\TextColumn::make('priority')
                    ->label('Priority')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('match_type_display')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Countries' => 'success',
                        'Continents' => 'warning',
                        'Regions' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('match_values_display')
                    ->label('Target')
                    ->wrap()
                    ->searchable(false),

                Tables\Columns\TextColumn::make('redirect_url')
                    ->label('Redirect To')
                    ->url(fn ($record) => $record->redirect_url, true)
                    ->color('primary')
                    ->limit(50),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('match_type')
                    ->options([
                        'country' => 'Countries',
                        'continent' => 'Continents',
                        'region' => 'Custom Regions',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->visible(fn () => app(GeolocationService::class)->isAvailable()),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('priority', 'asc')
            ->reorderable('priority')
            ->emptyStateHeading('No geo-targeting rules')
            ->emptyStateDescription('Create rules to redirect visitors based on their location')
            ->emptyStateIcon('heroicon-o-globe-alt');
    }

    protected function getCountryOptions(): array
    {
        // Common countries - you could expand this or load from a package
        return [
            'US' => 'United States',
            'CA' => 'Canada',
            'GB' => 'United Kingdom',
            'DE' => 'Germany',
            'FR' => 'France',
            'ES' => 'Spain',
            'IT' => 'Italy',
            'JP' => 'Japan',
            'CN' => 'China',
            'IN' => 'India',
            'BR' => 'Brazil',
            'MX' => 'Mexico',
            'AU' => 'Australia',
            'NZ' => 'New Zealand',
            'KR' => 'South Korea',
            'SG' => 'Singapore',
            'HK' => 'Hong Kong',
            'TW' => 'Taiwan',
            'NL' => 'Netherlands',
            'BE' => 'Belgium',
            'CH' => 'Switzerland',
            'SE' => 'Sweden',
            'NO' => 'Norway',
            'DK' => 'Denmark',
            'FI' => 'Finland',
            'PL' => 'Poland',
            'RU' => 'Russia',
            'UA' => 'Ukraine',
            'ZA' => 'South Africa',
            'AE' => 'United Arab Emirates',
            'SA' => 'Saudi Arabia',
            'AR' => 'Argentina',
            'CL' => 'Chile',
            'CO' => 'Colombia',
            'PE' => 'Peru',
            'VE' => 'Venezuela',
            'TH' => 'Thailand',
            'VN' => 'Vietnam',
            'PH' => 'Philippines',
            'ID' => 'Indonesia',
            'MY' => 'Malaysia',
            'PK' => 'Pakistan',
            'BD' => 'Bangladesh',
            'EG' => 'Egypt',
            'NG' => 'Nigeria',
            'KE' => 'Kenya',
            'IL' => 'Israel',
            'TR' => 'Turkey',
            'GR' => 'Greece',
            'PT' => 'Portugal',
            'CZ' => 'Czech Republic',
            'HU' => 'Hungary',
            'RO' => 'Romania',
            'AT' => 'Austria',
            'IE' => 'Ireland',
        ];
    }

    protected function getContinentOptions(): array
    {
        return [
            'AF' => 'Africa',
            'AN' => 'Antarctica',
            'AS' => 'Asia',
            'EU' => 'Europe',
            'NA' => 'North America',
            'OC' => 'Oceania',
            'SA' => 'South America',
        ];
    }

    protected function getRegionOptions(): array
    {
        return [
            'gdpr_zone' => 'GDPR Zone (EU + EEA)',
            'five_eyes' => 'Five Eyes (US, CA, GB, AU, NZ)',
            'apac_developed' => 'APAC Developed (JP, KR, SG, HK, TW)',
            'north_america' => 'North America (US, CA, MX)',
            'south_america' => 'South America',
            'middle_east' => 'Middle East',
        ];
    }
}
