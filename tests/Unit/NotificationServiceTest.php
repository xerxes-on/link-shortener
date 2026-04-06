<?php

namespace Tests\Unit;

use App\Mail\LinkHealthGroupNotification;
use App\Mail\LinkHealthOwnerNotification;
use App\Mail\MaintenanceNotification;
use App\Mail\SystemAlertNotification;
use App\Models\Link;
use App\Models\LinkNotification;
use App\Models\NotificationChannel;
use App\Models\NotificationGroup;
use App\Models\NotificationType;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private NotificationService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notificationService = app(NotificationService::class);
        Mail::fake();
        Http::fake();
    }

    public function test_sends_link_health_notifications_to_groups(): void
    {
        // Create notification type
        $notificationType = NotificationType::factory()->create([
            'name' => 'link_health',
            'default_groups' => [],
        ]);

        // Create a notification group with users and channels
        $group = NotificationGroup::factory()->create(['name' => 'Tech Team']);
        $user1 = User::factory()->create(['email' => 'admin@example.com']);
        $user2 = User::factory()->create(['email' => 'dev@example.com']);
        $group->users()->attach($user1, ['is_active' => 1]);
        $group->users()->attach($user2, ['is_active' => 1]);

        // Add a webhook channel
        $webhookChannel = NotificationChannel::factory()->create([
            'notification_group_id' => $group->id,
            'type' => 'webhook',
            'config' => ['url' => 'https://example.com/webhook', 'method' => 'POST'],
        ]);

        // Create failed links
        $link1 = Link::factory()->create([
            'original_url' => 'https://example.com/broken1',
            'health_status' => 'error',
            'health_check_message' => 'Connection timeout',
            'http_status_code' => 0,
        ]);

        $link2 = Link::factory()->create([
            'original_url' => 'https://example.com/broken2',
            'health_status' => 'error',
            'health_check_message' => 'Not found',
            'http_status_code' => 404,
        ]);

        // Assign links to notification group
        LinkNotification::factory()->create([
            'link_id' => $link1->id,
            'notification_group_id' => $group->id,
            'notification_type_id' => $notificationType->id,
        ]);

        LinkNotification::factory()->create([
            'link_id' => $link2->id,
            'notification_group_id' => $group->id,
            'notification_type_id' => $notificationType->id,
        ]);

        $failedLinks = collect([$link1, $link2]);

        // Send notifications
        $this->notificationService->sendLinkHealthNotifications($failedLinks);

        // Assert group emails were sent (one per user)
        Mail::assertSent(LinkHealthGroupNotification::class, function ($mail) use ($group) {
            return $mail->hasTo('admin@example.com') &&
                   $mail->groupName === $group->name &&
                   $mail->failedLinks->count() === 2;
        });

        Mail::assertSent(LinkHealthGroupNotification::class, function ($mail) use ($group) {
            return $mail->hasTo('dev@example.com') &&
                   $mail->groupName === $group->name &&
                   $mail->failedLinks->count() === 2;
        });

        // Assert webhook was called
        Http::assertSent(function ($request) {
            return $request->url() === 'https://example.com/webhook' &&
                   $request->method() === 'POST' &&
                   str_contains($request->body(), 'Link Health Alert');
        });
    }

    public function test_sends_link_health_notifications_to_owners(): void
    {
        // Create notification type with notify_link_owner enabled
        NotificationType::factory()->create([
            'name' => 'link_health',
            'notify_link_owner' => true,
            'default_groups' => [],
        ]);

        // Create failed link with owner
        $owner = User::factory()->create(['email' => 'owner@example.com', 'name' => 'Link Owner']);
        $link = Link::factory()->create([
            'created_by' => $owner->id,
            'original_url' => 'https://example.com/broken',
            'health_status' => 'error',
            'health_check_message' => 'Server error',
            'http_status_code' => 500,
        ]);

        $failedLinks = collect([$link]);

        // Send notifications
        $this->notificationService->sendLinkHealthNotifications($failedLinks);

        // Assert owner email was sent
        Mail::assertSent(LinkHealthOwnerNotification::class, function ($mail) use ($owner) {
            return $mail->hasTo($owner->email) &&
                   $mail->user->id === $owner->id;
        });
    }

    public function test_sends_system_alert_notifications(): void
    {
        // Create notification type for system alerts
        $group1 = NotificationGroup::factory()->create(['name' => 'System Admins']);
        $group2 = NotificationGroup::factory()->create(['name' => 'DevOps Team']);

        $notificationType = NotificationType::factory()->create([
            'name' => 'system_alert',
            'default_groups' => [$group1->id, $group2->id],
        ]);

        // Add users to groups
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $devops = User::factory()->create(['email' => 'devops@example.com']);
        $group1->users()->attach($admin, ['is_active' => 1]);
        $group2->users()->attach($devops, ['is_active' => 1]);

        // Add Slack channel to one group
        $slackChannel = NotificationChannel::factory()->create([
            'notification_group_id' => $group1->id,
            'type' => 'slack',
            'config' => ['webhook_url' => 'https://hooks.slack.com/test'],
        ]);

        // Send system alert
        $message = 'Critical system issue detected';
        $severity = 'high';
        $additionalData = ['affected_components' => ['API', 'Database']];

        $this->notificationService->sendSystemAlert($message, $severity, $additionalData);

        // Assert emails were sent to both groups
        Mail::assertSent(SystemAlertNotification::class, function ($mail) use ($admin) {
            return $mail->hasTo($admin->email);
        });

        Mail::assertSent(SystemAlertNotification::class, function ($mail) use ($devops) {
            return $mail->hasTo($devops->email);
        });

        // Assert Slack webhook was called with the subject
        Http::assertSent(function ($request) {
            return $request->url() === 'https://hooks.slack.com/test' &&
                   str_contains($request->body(), 'System Alert');
        });
    }

    public function test_sends_maintenance_notifications(): void
    {
        // Create notification type for maintenance
        $group = NotificationGroup::factory()->create(['name' => 'All Users']);
        $notificationType = NotificationType::factory()->create([
            'name' => 'maintenance',
            'default_groups' => [$group->id],
        ]);

        // Add users to group
        $user1 = User::factory()->create(['email' => 'user1@example.com']);
        $user2 = User::factory()->create(['email' => 'user2@example.com']);
        $group->users()->attach($user1, ['is_active' => 1]);
        $group->users()->attach($user2, ['is_active' => 1]);

        // Add Discord channel
        $discordChannel = NotificationChannel::factory()->create([
            'notification_group_id' => $group->id,
            'type' => 'discord',
            'config' => ['webhook_url' => 'https://discord.com/api/webhooks/test'],
        ]);

        // Send maintenance notification
        $message = 'Scheduled maintenance starting in 1 hour';
        $scheduledTime = now()->addHour();
        $additionalData = ['expected_duration' => '2 hours'];

        $this->notificationService->sendMaintenanceNotification($message, $scheduledTime, $additionalData);

        // Assert emails were sent
        Mail::assertSent(MaintenanceNotification::class, function ($mail) use ($user1) {
            return $mail->hasTo($user1->email);
        });

        Mail::assertSent(MaintenanceNotification::class, function ($mail) use ($user2) {
            return $mail->hasTo($user2->email);
        });

        // Assert Discord webhook was called with the subject
        Http::assertSent(function ($request) {
            return $request->url() === 'https://discord.com/api/webhooks/test' &&
                   str_contains($request->body(), 'Maintenance Notification');
        });
    }

    public function test_groups_links_by_notification_targets(): void
    {
        $notificationType = NotificationType::factory()->create(['name' => 'link_health']);

        // Create two groups
        $group1 = NotificationGroup::factory()->create(['name' => 'Group 1']);
        $group2 = NotificationGroup::factory()->create(['name' => 'Group 2']);

        // Create links
        $link1 = Link::factory()->create();
        $link2 = Link::factory()->create();
        $link3 = Link::factory()->create();

        // Assign links to groups
        LinkNotification::factory()->create([
            'link_id' => $link1->id,
            'notification_group_id' => $group1->id,
            'notification_type_id' => $notificationType->id,
        ]);

        LinkNotification::factory()->create([
            'link_id' => $link2->id,
            'notification_group_id' => $group1->id,
            'notification_type_id' => $notificationType->id,
        ]);

        LinkNotification::factory()->create([
            'link_id' => $link3->id,
            'notification_group_id' => $group2->id,
            'notification_type_id' => $notificationType->id,
        ]);

        $failedLinks = collect([$link1, $link2, $link3]);

        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->notificationService);
        $method = $reflection->getMethod('groupLinksByNotificationTargets');
        $method->setAccessible(true);

        $groupedNotifications = $method->invoke($this->notificationService, $failedLinks, $notificationType);

        // Assert grouping
        $this->assertArrayHasKey('groups', $groupedNotifications);
        $this->assertArrayHasKey('owners', $groupedNotifications);
        $this->assertCount(2, $groupedNotifications['groups']);
        $this->assertCount(2, $groupedNotifications['groups'][$group1->id]);
        $this->assertCount(1, $groupedNotifications['groups'][$group2->id]);
    }

    public function test_sends_webhook_notifications(): void
    {
        $channel = NotificationChannel::factory()->create([
            'type' => 'webhook',
            'config' => ['url' => 'https://example.com/webhook', 'method' => 'POST'],
        ]);

        $message = 'Test webhook message';

        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->notificationService);
        $method = $reflection->getMethod('sendWebhookNotification');
        $method->setAccessible(true);

        $method->invoke($this->notificationService, $channel->config, $message, []);

        Http::assertSent(function ($request) use ($message) {
            return $request->url() === 'https://example.com/webhook' &&
                   $request->method() === 'POST' &&
                   str_contains($request->body(), $message);
        });
    }

    public function test_sends_slack_notifications(): void
    {
        $channel = NotificationChannel::factory()->create([
            'type' => 'slack',
            'config' => ['webhook_url' => 'https://hooks.slack.com/test', 'channel' => '#alerts'],
        ]);

        $message = 'Test slack message';

        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->notificationService);
        $method = $reflection->getMethod('sendSlackNotification');
        $method->setAccessible(true);

        $method->invoke($this->notificationService, $channel->config, $message, []);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return $request->url() === 'https://hooks.slack.com/test' &&
                   isset($body['text']) &&
                   str_contains($body['text'], 'Test slack message') &&
                   $body['channel'] === '#alerts';
        });
    }

    public function test_sends_discord_notifications(): void
    {
        $channel = NotificationChannel::factory()->create([
            'type' => 'discord',
            'config' => ['webhook_url' => 'https://discord.com/api/webhooks/test'],
        ]);

        $message = 'Test discord message';

        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->notificationService);
        $method = $reflection->getMethod('sendDiscordNotification');
        $method->setAccessible(true);

        $method->invoke($this->notificationService, $channel->config, $message, []);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return $request->url() === 'https://discord.com/api/webhooks/test' &&
                   isset($body['content']) &&
                   str_contains($body['content'], 'Test discord message');
        });
    }

    public function test_sends_teams_notifications(): void
    {
        $channel = NotificationChannel::factory()->create([
            'type' => 'teams',
            'config' => ['webhook_url' => 'https://outlook.office.com/webhook/test'],
        ]);

        $message = 'Test teams message';

        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->notificationService);
        $method = $reflection->getMethod('sendTeamsNotification');
        $method->setAccessible(true);

        $method->invoke($this->notificationService, $channel->config, $message, []);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return $request->url() === 'https://outlook.office.com/webhook/test' &&
                   isset($body['summary']) &&
                   str_contains($body['summary'], 'Test teams message');
        });
    }

    public function test_only_sends_to_active_groups_and_channels(): void
    {
        $notificationType = NotificationType::factory()->create(['name' => 'link_health']);

        // Create active and inactive groups
        $activeGroup = NotificationGroup::factory()->create(['is_active' => true]);
        $inactiveGroup = NotificationGroup::factory()->create(['is_active' => false]);

        // Add users to both groups
        $user = User::factory()->create(['email' => 'test@example.com']);
        $activeGroup->users()->attach($user, ['is_active' => 1]);
        $inactiveGroup->users()->attach($user, ['is_active' => 1]);

        // Add active and inactive channels to active group
        $activeChannel = NotificationChannel::factory()->create([
            'notification_group_id' => $activeGroup->id,
            'type' => 'webhook',
            'config' => ['url' => 'https://active.com/webhook', 'method' => 'POST'],
            'is_active' => true,
        ]);

        $inactiveChannel = NotificationChannel::factory()->create([
            'notification_group_id' => $activeGroup->id,
            'type' => 'webhook',
            'config' => ['url' => 'https://inactive.com/webhook', 'method' => 'POST'],
            'is_active' => false,
        ]);

        // Create failed link
        $link = Link::factory()->create(['health_status' => 'error']);

        // Assign to both groups
        LinkNotification::factory()->create([
            'link_id' => $link->id,
            'notification_group_id' => $activeGroup->id,
            'notification_type_id' => $notificationType->id,
        ]);

        LinkNotification::factory()->create([
            'link_id' => $link->id,
            'notification_group_id' => $inactiveGroup->id,
            'notification_type_id' => $notificationType->id,
        ]);

        $failedLinks = collect([$link]);

        // Send notifications
        $this->notificationService->sendLinkHealthNotifications($failedLinks);

        // Assert only active group/channel was notified
        Mail::assertSent(LinkHealthGroupNotification::class, 1); // Only one email sent

        Http::assertSent(function ($request) {
            return $request->url() === 'https://active.com/webhook';
        });

        Http::assertNotSent(function ($request) {
            return $request->url() === 'https://inactive.com/webhook';
        });
    }

    public function test_handles_notification_errors_gracefully(): void
    {
        // Create a channel with invalid webhook URL
        $channel = NotificationChannel::factory()->create([
            'type' => 'webhook',
            'config' => ['url' => 'https://invalid-url-that-will-fail.com/webhook', 'method' => 'POST'],
        ]);

        Http::fake([
            'https://invalid-url-that-will-fail.com/*' => Http::response(null, 500),
        ]);

        $message = 'Test error handling';

        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->notificationService);
        $method = $reflection->getMethod('sendWebhookNotification');
        $method->setAccessible(true);

        // Should not throw exception
        $method->invoke($this->notificationService, $channel->config, $message, []);

        // Verify the request was attempted
        Http::assertSent(function ($request) {
            return $request->url() === 'https://invalid-url-that-will-fail.com/webhook';
        });
    }
}
