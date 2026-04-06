<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * @property-read Schema $profileForm
 * @property-read Schema $passwordForm
 */
class UserProfile extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationLabel = 'Profile';

    protected static ?string $title = 'My Profile';

    protected string $view = 'filament.pages.user-profile';

    protected static bool $shouldRegisterNavigation = false;

    public ?array $profileData = [];

    public ?array $passwordData = [];

    public function mount(): void
    {
        $user = auth()->user();

        $this->profileForm->fill([
            'name' => $user->name,
            'email' => $user->email,
            'timezone' => $user->timezone ?? 'UTC',
        ]);
    }

    protected function getForms(): array
    {
        return [
            'profileForm',
            'passwordForm',
        ];
    }

    public function profileForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Profile Information')
                    ->description('Update your account profile information.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email Address')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique('users', 'email', auth()->user()),
                        Select::make('timezone')
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
                    ])
                    ->columns(2),
            ])
            ->statePath('profileData');
    }

    public function passwordForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Update Password')
                    ->description('Ensure your account is using a long, random password to stay secure.')
                    ->schema([
                        TextInput::make('current_password')
                            ->label('Current Password')
                            ->password()
                            ->required()
                            ->rules([
                                function (string $attribute, $value, \Closure $fail) {
                                    if (! Hash::check($value, auth()->user()->password)) {
                                        $fail('The current password is incorrect.');
                                    }
                                },
                            ]),
                        TextInput::make('password')
                            ->label('New Password')
                            ->password()
                            ->required()
                            ->rule(Password::default())
                            ->same('password_confirmation')
                            ->live(onBlur: true),
                        TextInput::make('password_confirmation')
                            ->label('Confirm Password')
                            ->password()
                            ->required()
                            ->dehydrated(false),
                    ]),
            ])
            ->statePath('passwordData');
    }

    public function updateProfile(): void
    {
        $data = $this->profileForm->getState();

        auth()->user()->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'timezone' => $data['timezone'],
        ]);

        Notification::make()
            ->title('Profile updated successfully')
            ->success()
            ->send();
    }

    public function updatePassword(): void
    {
        $data = $this->passwordForm->getState();

        auth()->user()->update([
            'password' => Hash::make($data['password']),
        ]);

        // Clear the password form
        $this->passwordData = [];

        Notification::make()
            ->title('Password updated successfully')
            ->success()
            ->send();
    }
}
