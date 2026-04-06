<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-users';

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(User::class, 'email', ignoreRecord: true)
                    ->maxLength(255),

                Forms\Components\Select::make('timezone')
                    ->label('Timezone')
                    ->options(function () {
                        $timezones = [];
                        foreach (timezone_identifiers_list() as $timezone) {
                            $timezones[$timezone] = $timezone;
                        }

                        return $timezones;
                    })
                    ->default('UTC')
                    ->searchable()
                    ->required()
                    ->helperText('Select your preferred timezone for displaying dates and times'),

                Forms\Components\Select::make('roles')
                    ->label('Roles')
                    ->multiple()
                    ->options(function () {
                        // Don't show super_admin role unless current user is super_admin
                        $roles = Role::pluck('name', 'name');

                        if (! auth()->user()->hasRole('super_admin')) {
                            $roles = $roles->except('super_admin');
                        }

                        return $roles;
                    })
                    ->required()
                    ->default(['user'])
                    ->helperText('Assign one or more roles to this user')
                    ->preload(),

                Forms\Components\TextInput::make('password')
                    ->password()
                    ->default(fn () => Str::password(12))
                    ->required(fn (string $context): bool => $context === 'create')
                    ->dehydrated(fn ($state) => filled($state))
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->revealable()
                    ->helperText(fn (string $context): string => $context === 'edit'
                        ? __('filament.user.password_edit_help')
                        : __('filament.user.password_create_help')
                    )
                    ->suffixAction(
                        fn (string $context) => $context === 'create'
                            ? Actions\Action::make('regenerate')
                                ->icon('heroicon-o-arrow-path')
                                ->tooltip(__('filament.user.regenerate_password'))
                                ->action(fn ($set) => $set('password', Str::password(12)))
                            : null
                    ),

                Forms\Components\Checkbox::make('send_welcome_email')
                    ->label(__('filament.user.send_welcome_email'))
                    ->default(true)
                    ->visible(fn () => static::isEmailConfigured())
                    ->hiddenOn('edit')
                    ->columnSpanFull(),

                Forms\Components\Placeholder::make('email_not_configured')
                    ->hiddenLabel()
                    ->content(__('filament.user.email_not_configured'))
                    ->visible(fn () => ! static::isEmailConfigured())
                    ->hiddenOn('edit')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('timezone')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'super_admin' => 'danger',
                        'admin' => 'warning',
                        'user' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state))
                    ),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload(),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    ->visible(fn (User $record) => auth()->user()->hasRole('super_admin') &&
                        ! $record->hasRole('super_admin')
                    ),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()->hasRole('super_admin')),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    /**
     * Check if email is properly configured for sending emails
     */
    public static function isEmailConfigured(): bool
    {
        $panelProvider = new AdminPanelProvider(app());

        return $panelProvider->isEmailConfigured();
    }
}
