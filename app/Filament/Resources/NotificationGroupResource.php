<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\NotificationsCluster;
use App\Filament\Resources\NotificationGroupResource\Pages;
use App\Filament\Resources\NotificationGroupResource\RelationManagers;
use App\Models\NotificationGroup;
use App\Models\User;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class NotificationGroupResource extends Resource
{
    protected static ?string $model = NotificationGroup::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $cluster = NotificationsCluster::class;

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->schema([
                Schemas\Components\Section::make('Group Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('is_active')
                            ->default(true)
                            ->helperText('Inactive groups will not send notifications'),
                    ])
                    ->columns(2),

                Schemas\Components\Section::make('Notification Targets')
                    ->description('Configure who and how this group should be notified')
                    ->schema([
                        Forms\Components\CheckboxList::make('users')
                            ->relationship('users', 'name')
                            ->options(User::pluck('name', 'id'))
                            ->columns(2)
                            ->helperText('Users will be notified via their email addresses'),

                        Forms\Components\Repeater::make('channels')
                            ->relationship('channels')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->placeholder('e.g., "Dev Team Slack", "Critical Alerts Webhook"'),

                                Forms\Components\Select::make('type')
                                    ->options([
                                        'webhook' => 'Webhook',
                                        'slack' => 'Slack',
                                        'discord' => 'Discord',
                                        'teams' => 'Microsoft Teams',
                                    ])
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Schemas\Components\Utilities\Set $set) {
                                        $set('config', []);
                                    }),

                                Forms\Components\TextInput::make('config.url')
                                    ->label('Webhook URL')
                                    ->url()
                                    ->required()
                                    ->visible(fn (Schemas\Components\Utilities\Get $get) => $get('type') === 'webhook'),

                                Forms\Components\Select::make('config.method')
                                    ->label('HTTP Method')
                                    ->options(['GET' => 'GET', 'POST' => 'POST'])
                                    ->default('POST')
                                    ->visible(fn (Schemas\Components\Utilities\Get $get) => $get('type') === 'webhook'),

                                Forms\Components\TextInput::make('config.webhook_url')
                                    ->label('Slack Webhook URL')
                                    ->url()
                                    ->required()
                                    ->visible(fn (Schemas\Components\Utilities\Get $get) => $get('type') === 'slack'),

                                Forms\Components\TextInput::make('config.channel')
                                    ->label('Channel Name (optional)')
                                    ->visible(fn (Schemas\Components\Utilities\Get $get) => $get('type') === 'slack'),

                                Forms\Components\TextInput::make('config.webhook_url')
                                    ->label('Discord Webhook URL')
                                    ->url()
                                    ->required()
                                    ->visible(fn (Schemas\Components\Utilities\Get $get) => $get('type') === 'discord'),

                                Forms\Components\TextInput::make('config.webhook_url')
                                    ->label('Teams Webhook URL')
                                    ->url()
                                    ->required()
                                    ->visible(fn (Schemas\Components\Utilities\Get $get) => $get('type') === 'teams'),

                                Forms\Components\Toggle::make('is_active')
                                    ->default(true)
                                    ->label('Active'),
                            ])
                            ->columns(2)
                            ->collapsed()
                            ->defaultItems(0)
                            ->minItems(0)
                            ->addActionLabel('Add Channel')
                            ->helperText('Add webhooks, Slack channels, etc. for this notification group'),
                    ])
                    ->collapsible(),

                Schemas\Components\Section::make('Advanced Settings')
                    ->schema([
                        Forms\Components\KeyValue::make('settings')
                            ->helperText('Additional JSON configuration for this group')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();

                        return strlen($state) > 50 ? $state : null;
                    }),

                Tables\Columns\TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Users')
                    ->sortable(),

                Tables\Columns\TextColumn::make('channels_count')
                    ->counts('channels')
                    ->label('Channels')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\UsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotificationGroups::route('/'),
            'create' => Pages\CreateNotificationGroup::route('/create'),
            'edit' => Pages\EditNotificationGroup::route('/{record}/edit'),
        ];
    }
}
