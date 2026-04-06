<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Models\User;
use App\Notifications\WelcomeUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WelcomeUserEmailTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles and permissions
        $superAdminRole = Role::create(['name' => 'super_admin']);
        Role::create(['name' => 'user']);

        // Create basic user permissions manually
        $permissions = [
            'view_user',
            'view_any_user',
            'create_user',
            'update_user',
            'delete_user',
            'delete_any_user',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Give super_admin all permissions
        $superAdminRole->givePermissionTo($permissions);

        // Create admin user
        $this->admin = User::factory()->create();
        $this->admin->assignRole('super_admin');

        $this->actingAs($this->admin);
    }

    public function test_password_field_has_auto_generated_value_on_create(): void
    {
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', 'smtp.gmail.com');

        $response = $this->get('/admin/users/create');
        $response->assertStatus(200);

        // Check that the password field has a value (auto-generated)
        $response->assertSee('password');
        $response->assertSee('regenerate');
        $response->assertSee(__('filament.user.password_create_help'));
    }

    public function test_send_welcome_email_checkbox_visible_when_email_configured(): void
    {
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', 'smtp.gmail.com');

        $response = $this->get('/admin/users/create');
        $response->assertStatus(200);
        $response->assertSee(__('filament.user.send_welcome_email'));
        $response->assertDontSee(__('filament.user.email_not_configured'));
    }

    public function test_send_welcome_email_checkbox_hidden_when_email_not_configured(): void
    {
        Config::set('mail.default', 'log');

        $response = $this->get('/admin/users/create');
        $response->assertStatus(200);
        $response->assertDontSee(__('filament.user.send_welcome_email'));
        $response->assertSee(__('filament.user.email_not_configured'));
    }

    public function test_welcome_email_sent_when_checkbox_checked(): void
    {
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', 'smtp.gmail.com');

        Notification::fake();

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Test User',
                'email' => 'newuser@example.com',
                'timezone' => 'UTC',
                'password' => 'secure-password-123',
                'roles' => ['user'],
                'send_welcome_email' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // Check user was created
        $user = User::where('email', 'newuser@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('Test User', $user->name);

        // Check notification was sent
        Notification::assertSentTo($user, WelcomeUser::class);
    }

    public function test_welcome_email_not_sent_when_checkbox_unchecked(): void
    {
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', 'smtp.gmail.com');

        Notification::fake();

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Test User',
                'email' => 'newuser@example.com',
                'timezone' => 'UTC',
                'password' => 'secure-password-123',
                'roles' => ['user'],
                'send_welcome_email' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // Check user was created
        $user = User::where('email', 'newuser@example.com')->first();
        $this->assertNotNull($user);

        // Check notification was NOT sent
        Notification::assertNotSentTo($user, WelcomeUser::class);
    }

    public function test_welcome_email_not_sent_when_email_not_configured(): void
    {
        Config::set('mail.default', 'log');

        Notification::fake();

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Test User',
                'email' => 'newuser@example.com',
                'timezone' => 'UTC',
                'password' => 'secure-password-123',
                'roles' => ['user'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // Check user was created
        $user = User::where('email', 'newuser@example.com')->first();
        $this->assertNotNull($user);

        // Check notification was NOT sent (checkbox not even available)
        Notification::assertNotSentTo($user, WelcomeUser::class);
    }

    public function test_welcome_email_notification_instance_can_be_created(): void
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $notification = new WelcomeUser;
        $this->assertInstanceOf(WelcomeUser::class, $notification);

        // Test that the notification uses mail channel
        $channels = $notification->via($user);
        $this->assertEquals(['mail'], $channels);
    }

    public function test_password_field_shows_edit_help_text_on_edit(): void
    {
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', 'smtp.gmail.com');

        $user = User::factory()->create();

        $response = $this->get("/admin/users/{$user->id}/edit");
        $response->assertStatus(200);
        $response->assertSee(__('filament.user.password_edit_help'));
        $response->assertDontSee(__('filament.user.password_create_help'));
        // Email checkbox should not be visible on edit
        $response->assertDontSee(__('filament.user.send_welcome_email'));
    }
}
