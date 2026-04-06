<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\NotificationsCluster;
use App\Filament\Resources\NotificationChannelResource\Pages;
use App\Models\NotificationChannel;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class NotificationChannelResource extends Resource
{
    protected static ?string $model = NotificationChannel::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $cluster = NotificationsCluster::class;

    protected static ?int $navigationSort = 2;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $label = 'Channels';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->schema([
                Schemas\Components\Section::make('Channel Details')
                    ->schema([
                        Forms\Components\Select::make('notification_group_id')
                            ->label('Notification Group')
                            ->relationship('notificationGroup', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),

                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('type')
                            ->options(NotificationChannel::getAvailableTypes())
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Schemas\Components\Utilities\Set $set) {
                                $set('config', []);
                            }),

                        Forms\Components\Toggle::make('is_active')
                            ->default(true),
                    ])
                    ->columns(2),

                Schemas\Components\Section::make('Configuration')
                    ->schema([
                        // Email configuration
                        Forms\Components\TextInput::make('config.email')
                            ->label('Email Address')
                            ->email()
                            ->required()
                            ->visible(fn (Schemas\Components\Utilities\Get $get) => $get('type') === 'email'),

                        Forms\Components\TextInput::make('config.name')
                            ->label('Display Name')
                            ->visible(fn (Schemas\Components\Utilities\Get $get) => $get('type') === 'email'),

                        // Webhook configuration
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

                        Forms\Components\Textarea::make('config.headers')
                            ->label('Custom Headers (JSON)')
                            ->helperText('Additional HTTP headers as JSON object')
                            ->visible(fn (Schemas\Components\Utilities\Get $get) => $get('type') === 'webhook'),

                        // Slack configuration
                        Forms\Components\TextInput::make('config.webhook_url')
                            ->label('Slack Webhook URL')
                            ->url()
                            ->required()
                            ->visible(fn (Schemas\Components\Utilities\Get $get) => $get('type') === 'slack'),

                        Forms\Components\TextInput::make('config.channel')
                            ->label('Channel Name')
                            ->helperText('Override default channel (optional)')
                            ->visible(fn (Schemas\Components\Utilities\Get $get) => $get('type') === 'slack'),

                        Forms\Components\TextInput::make('config.username')
                            ->label('Bot Username')
                            ->helperText('Custom username for messages (optional)')
                            ->visible(fn (Schemas\Components\Utilities\Get $get) => in_array($get('type'), ['slack', 'discord'])),

                        // Discord configuration
                        Forms\Components\TextInput::make('config.webhook_url')
                            ->label('Discord Webhook URL')
                            ->url()
                            ->required()
                            ->visible(fn (Schemas\Components\Utilities\Get $get) => $get('type') === 'discord'),

                        // Teams configuration
                        Forms\Components\TextInput::make('config.webhook_url')
                            ->label('Teams Webhook URL')
                            ->url()
                            ->required()
                            ->visible(fn (Schemas\Components\Utilities\Get $get) => $get('type') === 'teams'),
                    ])
                    ->columns(1),

                Schemas\Components\Section::make('Additional Settings')
                    ->schema([
                        Forms\Components\KeyValue::make('settings')
                            ->helperText('Channel-specific settings and overrides')
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
                Tables\Columns\TextColumn::make('notificationGroup.name')
                    ->label('Group')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'primary' => 'email',
                        'warning' => 'webhook',
                        'success' => 'slack',
                        'info' => 'discord',
                        'secondary' => 'teams',
                    ]),

                Tables\Columns\TextColumn::make('config_display')
                    ->label('Configuration')
                    ->getStateUsing(function (NotificationChannel $record): string {
                        return $record->config_display;
                    })
                    ->limit(50),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('notification_group_id')
                    ->label('Group')
                    ->relationship('notificationGroup', 'name'),

                Tables\Filters\SelectFilter::make('type')
                    ->options(NotificationChannel::getAvailableTypes()),

                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotificationChannels::route('/'),
            'create' => Pages\CreateNotificationChannel::route('/create'),
            'view' => Pages\ViewNotificationChannel::route('/{record}'),
            'edit' => Pages\EditNotificationChannel::route('/{record}/edit'),
        ];
    }
}
