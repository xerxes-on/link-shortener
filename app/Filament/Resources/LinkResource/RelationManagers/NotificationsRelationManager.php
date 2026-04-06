<?php

namespace App\Filament\Resources\LinkResource\RelationManagers;

use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class NotificationsRelationManager extends RelationManager
{
    protected static string $relationship = 'linkNotifications';

    protected static ?string $title = 'Notification Settings';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Schemas\Components\Section::make('Notification Assignment')
                    ->schema([
                        Forms\Components\Select::make('notification_group_id')
                            ->label('Notification Group')
                            ->relationship('notificationGroup', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->helperText('Select which group should be notified about this link'),

                        Forms\Components\Select::make('notification_type_id')
                            ->label('Notification Type')
                            ->relationship('notificationType', 'display_name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->helperText('Select the type of notifications to send'),

                        Forms\Components\Toggle::make('is_active')
                            ->default(true)
                            ->helperText('Enable or disable notifications for this assignment'),
                    ])
                    ->columns(1),

                Schemas\Components\Section::make('Notification Settings')
                    ->schema([
                        Forms\Components\KeyValue::make('settings')
                            ->helperText('Override default notification settings for this specific link')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('notificationGroup.name')
            ->modifyQueryUsing(fn ($query) => $query->with(['notificationGroup.users', 'notificationGroup.channels']))
            ->columns([
                Tables\Columns\TextColumn::make('notificationGroup.name')
                    ->label('Group')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('notificationType.display_name')
                    ->label('Type')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('notificationGroup.users_count')
                    ->label('Users')
                    ->getStateUsing(fn ($record) => $record->notificationGroup?->users()->count() ?? 0)
                    ->badge(),

                Tables\Columns\TextColumn::make('notificationGroup.channels_count')
                    ->label('Channels')
                    ->getStateUsing(fn ($record) => $record->notificationGroup?->channels()->count() ?? 0)
                    ->badge(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('notification_group_id')
                    ->label('Group')
                    ->relationship('notificationGroup', 'name'),

                Tables\Filters\SelectFilter::make('notification_type_id')
                    ->label('Type')
                    ->relationship('notificationType', 'display_name'),

                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->modalHeading('Add Notification Assignment')
                    ->modalDescription('Assign a notification group to receive alerts for this specific link.'),
            ])
            ->actions([
                Actions\EditAction::make()
                    ->modalHeading('Edit Notification Assignment'),
                Actions\DeleteAction::make(),
                Actions\Action::make('toggle_active')
                    ->label(fn ($record) => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn ($record) => $record->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn ($record) => $record->is_active ? 'warning' : 'success')
                    ->action(function ($record) {
                        $record->update(['is_active' => ! $record->is_active]);
                    })
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No Notifications Configured')
            ->emptyStateDescription('This link has no notification groups assigned. Add one to receive health alerts and other notifications.')
            ->emptyStateIcon('heroicon-o-bell-slash');
    }
}
