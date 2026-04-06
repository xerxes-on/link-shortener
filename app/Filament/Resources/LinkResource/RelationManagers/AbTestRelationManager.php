<?php

namespace App\Filament\Resources\LinkResource\RelationManagers;

use App\Models\AbTest;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;

class AbTestRelationManager extends RelationManager
{
    protected static string $relationship = 'abTest';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $title = 'A/B Test';

    protected static ?string $modelLabel = 'A/B Test';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g., Homepage vs Landing Page'),

                Forms\Components\Textarea::make('description')
                    ->placeholder('Describe what this A/B test is measuring...')
                    ->rows(3),

                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->helperText('Enable or disable this A/B test'),

                Forms\Components\DateTimePicker::make('starts_at')
                    ->label('Start Date')
                    ->helperText('Leave empty to start immediately'),

                Forms\Components\DateTimePicker::make('ends_at')
                    ->label('End Date')
                    ->helperText('Leave empty to run indefinitely'),

                Forms\Components\Repeater::make('variants')
                    ->relationship('variants')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->placeholder('e.g., Control, Variant A'),

                        Forms\Components\TextInput::make('url')
                            ->required()
                            ->url()
                            ->placeholder('https://example.com/page'),

                        Forms\Components\TextInput::make('weight')
                            ->required()
                            ->numeric()
                            ->default(50)
                            ->suffix('%')
                            ->minValue(1)
                            ->maxValue(100)
                            ->helperText('Traffic percentage for this variant'),
                    ])
                    ->defaultItems(2)
                    ->minItems(2)
                    ->maxItems(10)
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? 'Variant')
                    ->addActionLabel('Add Variant')
                    ->helperText('Create different versions to test. Weights should add up to 100%.')
                    ->reorderable(false)
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if (is_array($state)) {
                            $totalWeight = collect($state)->sum('weight');
                            if ($totalWeight > 0 && $totalWeight !== 100) {
                                Notification::make()
                                    ->warning()
                                    ->title('Weight Warning')
                                    ->body("Current total weight: {$totalWeight}%. Should be exactly 100%.")
                                    ->send();
                            }
                        }
                    }),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),

                Tables\Columns\TextColumn::make('variants_count')
                    ->counts('variants')
                    ->label('Variants'),

                Tables\Columns\TextColumn::make('total_clicks')
                    ->getStateUsing(function (AbTest $record): int {
                        return $record->variants->sum('click_count');
                    })
                    ->label('Total Clicks')
                    ->numeric(),

                Tables\Columns\TextColumn::make('starts_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Immediate'),

                Tables\Columns\TextColumn::make('ends_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('No end date'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Tests'),
            ])
            ->headerActions([
                Actions\CreateAction::make(),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
                Actions\Action::make('view_stats')
                    ->label('View Stats')
                    ->icon('heroicon-o-chart-bar')
                    ->color('info')
                    ->modalHeading(fn (AbTest $record): string => "A/B Test Statistics: {$record->name}")
                    ->modalContent(function (AbTest $record): View {
                        $variants = $record->variants()->withCount('clicks')->get();
                        $totalClicks = $variants->sum('clicks_count');

                        return view('filament.modals.ab-test-stats', [
                            'abTest' => $record,
                            'variants' => $variants,
                            'totalClicks' => $totalClicks,
                        ]);
                    })
                    ->modalWidth('4xl'),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected function canCreate(): bool
    {
        // Only allow one A/B test per link
        return $this->getOwnerRecord()->abTest === null;
    }
}
