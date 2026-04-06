<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\NotificationsCluster;
use App\Filament\Resources\NotificationRuleResource\Pages;
use App\Models\LinkGroup;
use App\Models\NotificationGroup;
use App\Models\NotificationType;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class NotificationRuleResource extends Resource
{
    protected static ?string $model = NotificationType::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $cluster = NotificationsCluster::class;

    protected static ?int $navigationSort = 3;

    protected static ?string $label = 'Notification Rules';

    protected static ?string $pluralLabel = 'Notification Rules';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->schema([
                Schemas\Components\Section::make('Rule Details')
                    ->schema([
                        Forms\Components\TextInput::make('display_name')
                            ->label('Rule Name')
                            ->required()
                            ->placeholder('e.g., "Link Health Monitoring", "System Alerts"'),

                        Forms\Components\Textarea::make('description')
                            ->rows(2)
                            ->placeholder('Describe when this notification should be sent'),

                        Forms\Components\Toggle::make('is_active')
                            ->default(true)
                            ->helperText('Enable or disable this notification rule'),
                    ])
                    ->columns(1),

                Schemas\Components\Section::make('When to Notify')
                    ->schema([
                        Forms\Components\Select::make('name')
                            ->label('Notification Type')
                            ->options([
                                'link_health' => 'Link Health (when links go down/up)',
                                'system_alert' => 'System Alerts (critical issues)',
                                'maintenance' => 'Maintenance Notifications',
                                'custom' => 'Custom Event',
                            ])
                            ->required()
                            ->live(),

                        Forms\Components\TextInput::make('name')
                            ->label('Custom Event Name')
                            ->visible(fn (Schemas\Components\Utilities\Get $get) => $get('name') === 'custom')
                            ->helperText('Enter a unique identifier for this custom notification type'),
                    ])
                    ->columns(1),

                Schemas\Components\Section::make('Who to Notify')
                    ->schema([
                        Forms\Components\Select::make('default_groups')
                            ->label('Default Notification Groups')
                            ->multiple()
                            ->options(NotificationGroup::pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->helperText('These groups will be notified for all links unless overridden'),

                        Forms\Components\Checkbox::make('notify_link_owner')
                            ->label('Also notify link owner')
                            ->helperText('Send notifications to the user who created each link')
                            ->default(false),

                        Forms\Components\Select::make('apply_to_link_groups')
                            ->label('Apply to Link Groups')
                            ->multiple()
                            ->options(LinkGroup::pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->helperText('Leave empty to apply to all links, or select specific groups'),
                    ])
                    ->columns(1),

                Schemas\Components\Section::make('Notification Settings')
                    ->schema([
                        Forms\Components\Checkbox::make('exclude_blocked_links')
                            ->label('Exclude blocked links')
                            ->helperText('Don\'t send notifications for links with "blocked" status (often due to server IP restrictions)')
                            ->default(true)
                            ->visible(fn (Schemas\Components\Utilities\Get $get) => $get('name') === 'link_health'),

                        Forms\Components\Placeholder::make('batching_info')
                            ->label('Email Delivery')
                            ->content('Group notifications: One summary email with all failed links. Link owner notifications: Separate email containing only their links.')
                            ->visible(fn (Schemas\Components\Utilities\Get $get) => $get('name') === 'link_health'),

                        Forms\Components\Placeholder::make('link_health_info')
                            ->label('Link Health Notifications')
                            ->content('Sent when: Link health checks fail (HTTP errors, timeouts). Contains: Link URL, error message, HTTP status code, last working time.')
                            ->visible(fn (Schemas\Components\Utilities\Get $get) => $get('name') === 'link_health'),

                        Forms\Components\Placeholder::make('system_alert_info')
                            ->label('System Alert Notifications')
                            ->content('Sent when: Critical system issues occur. Contains: Alert description, severity level, affected components, recommended actions.')
                            ->visible(fn (Schemas\Components\Utilities\Get $get) => $get('name') === 'system_alert'),

                        Forms\Components\Placeholder::make('maintenance_info')
                            ->label('Maintenance Notifications')
                            ->content('Sent when: Scheduled maintenance begins/ends. Contains: Maintenance window, affected services, expected duration, status updates.')
                            ->visible(fn (Schemas\Components\Utilities\Get $get) => $get('name') === 'maintenance'),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Rule Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('name')
                    ->label('Type')
                    ->colors([
                        'success' => 'link_health',
                        'danger' => 'system_alert',
                        'warning' => 'maintenance',
                        'info' => fn ($state) => ! in_array($state, ['link_health', 'system_alert', 'maintenance']),
                    ]),

                Tables\Columns\TextColumn::make('default_groups')
                    ->label('Default Groups')
                    ->formatStateUsing(function ($state, $record) {
                        if (empty($state)) {
                            return 'No groups assigned';
                        }

                        $groups = $record->getDefaultGroups();

                        return $groups->pluck('name')->join(', ');
                    })
                    ->limit(50)
                    ->tooltip(function ($state, $record) {
                        if (empty($state)) {
                            return null;
                        }

                        $groups = $record->getDefaultGroups();
                        $names = $groups->pluck('name')->join(', ');

                        return strlen($names) > 50 ? $names : null;
                    }),

                Tables\Columns\IconColumn::make('notify_link_owner')
                    ->label('Notify Owner')
                    ->boolean()
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueIcon('heroicon-o-check'),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('name')
                    ->label('Type')
                    ->options([
                        'link_health' => 'Link Health',
                        'system_alert' => 'System Alert',
                        'maintenance' => 'Maintenance',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active'),

                Tables\Filters\SelectFilter::make('default_groups')
                    ->label('Has Group')
                    ->options(NotificationGroup::pluck('name', 'id'))
                    ->query(function ($query, array $data) {
                        if (! $data['value']) {
                            return $query;
                        }

                        return $query->whereJsonContains('default_groups', (int) $data['value']);
                    }),
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
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotificationRules::route('/'),
            'create' => Pages\CreateNotificationRule::route('/create'),
            'edit' => Pages\EditNotificationRule::route('/{record}/edit'),
        ];
    }
}
