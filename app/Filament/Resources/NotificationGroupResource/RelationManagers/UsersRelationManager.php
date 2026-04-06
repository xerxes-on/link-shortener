<?php

namespace App\Filament\Resources\NotificationGroupResource\RelationManagers;

use App\Models\User;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Select::make('recordId')
                    ->label('User')
                    ->options(User::pluck('name', 'id'))
                    ->searchable()
                    ->required()
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')
                            ->required(),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required(),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->required(),
                    ]),

                Forms\Components\Toggle::make('is_active')
                    ->default(true)
                    ->helperText('User will only receive notifications when active in this group'),
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

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('pivot.is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('pivot.created_at')
                    ->label('Added')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->attribute('pivot.is_active'),
            ])
            ->headerActions([
                Actions\AttachAction::make()
                    ->form(fn (Actions\AttachAction $action): array => [
                        $action->getRecordSelect()
                            ->searchable()
                            ->preload(),
                        Forms\Components\Toggle::make('is_active')
                            ->default(true),
                    ]),
            ])
            ->actions([
                Actions\DetachAction::make(),
                Actions\Action::make('toggle_active')
                    ->label(fn ($record) => $record->pivot->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn ($record) => $record->pivot->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn ($record) => $record->pivot->is_active ? 'warning' : 'success')
                    ->action(function ($record, RelationManager $livewire) {
                        $livewire->getOwnerRecord()
                            ->users()
                            ->updateExistingPivot($record->id, [
                                'is_active' => ! $record->pivot->is_active,
                            ]);
                    })
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}
