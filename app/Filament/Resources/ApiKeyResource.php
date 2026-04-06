<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ApiKeyResource\Pages;
use App\Models\ApiKey;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ApiKeyResource extends Resource
{
    protected static ?string $model = ApiKey::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-key';

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->schema([
                Schemas\Components\Section::make('API Key Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Key Name')
                            ->required()
                            ->placeholder('Production API Key')
                            ->helperText('A descriptive name to identify this key'),
                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Expiration Date')
                            ->nullable()
                            ->minDate(now())
                            ->helperText('Leave empty for no expiration'),
                        Forms\Components\Select::make('permissions')
                            ->label('Permissions')
                            ->multiple()
                            ->options([
                                'links:create' => 'Create Links',
                                'links:read' => 'Read Links',
                                'links:update' => 'Update Links',
                                'links:delete' => 'Delete Links',
                                'stats:read' => 'Read Statistics',
                                'groups:create' => 'Create Groups',
                                'groups:read' => 'Read Groups',
                                'groups:update' => 'Update Groups',
                                'groups:delete' => 'Delete Groups',
                            ])
                            ->default(['links:create', 'links:read', 'stats:read', 'groups:read'])
                            ->helperText('Select what this API key can do. Leave empty for full access.'),
                    ])->columns(2),
                Forms\Components\Hidden::make('created_by')
                    ->default(auth()->id()),
                Forms\Components\Hidden::make('key_hash'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Key Name')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('api_key')
                    ->label('API Key')
                    ->formatStateUsing(function ($state) {
                        if (! $state) {
                            return 'Legacy Key (Hidden)';
                        }

                        return $state;
                    })
                    ->copyable()
                    ->copyMessage('API key copied to clipboard')
                    ->toggleable()
                    ->fontFamily('mono')
                    ->size('sm'),
                Tables\Columns\TextColumn::make('permissions')
                    ->label('Permissions')
                    ->formatStateUsing(function ($state, $record) {
                        $permissions = $record->permissions;

                        if (! $permissions || empty($permissions)) {
                            return 'Full Access';
                        }

                        if (is_array($permissions)) {
                            // Map permission keys to readable labels
                            $permissionLabels = [
                                'links:create' => 'Create',
                                'links:read' => 'Read',
                                'links:update' => 'Update',
                                'links:delete' => 'Delete',
                                'stats:read' => 'Stats',
                                'groups:create' => 'G-Create',
                                'groups:read' => 'G-Read',
                                'groups:update' => 'G-Update',
                                'groups:delete' => 'G-Delete',
                            ];

                            $labels = array_map(function ($perm) use ($permissionLabels) {
                                return $permissionLabels[$perm] ?? $perm;
                            }, $permissions);

                            return implode(' • ', $labels);
                        }

                        return 'Full Access';
                    })
                    ->html()
                    ->color(function ($state, $record) {
                        $permissions = $record->permissions;

                        return ! $permissions || empty($permissions) ? 'warning' : 'success';
                    }),
                Tables\Columns\TextColumn::make('last_used_at')
                    ->label('Last Used')
                    ->dateTime('M j, Y g:i A')
                    ->placeholder('Never')
                    ->sortable(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime('M j, Y')
                    ->placeholder('Never')
                    ->color(fn ($record) => $record->isExpired() ? 'danger' : null),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Owner')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('expired')
                    ->query(fn (Builder $query) => $query->where('expires_at', '<', now()))
                    ->label('Expired Keys'),
                Tables\Filters\Filter::make('unused')
                    ->query(fn (Builder $query) => $query->whereNull('last_used_at'))
                    ->label('Never Used'),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListApiKeys::route('/'),
            'create' => Pages\CreateApiKey::route('/create'),
            'edit' => Pages\EditApiKey::route('/{record}/edit'),
        ];
    }
}
