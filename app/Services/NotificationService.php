<?php

namespace App\Services;

use App\Mail\LinkHealthGroupNotification;
use App\Mail\LinkHealthOwnerNotification;
use App\Mail\MaintenanceNotification as MaintenanceNotificationMailable;
use App\Mail\SystemAlertNotification as SystemAlertNotificationMailable;
use App\Models\Link;
use App\Models\NotificationGroup;
use App\Models\NotificationType;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    /**
     * Send link health notifications for failed links
     */
    public function sendLinkHealthNotifications(Collection $failedLinks, ?Collection $previouslyFailedLinks = null): void
    {
        $notificationType = NotificationType::where('name', 'link_health')
            ->where('is_active', true)
            ->first();

        if (! $notificationType) {
            Log::warning('Link health notification type not found or inactive');

            return;
        }

        // Group failed links by notification groups and link owners
        $groupedNotifications = $this->groupLinksByNotificationTargets($failedLinks, $notificationType);

        // Also group previously failed links to properly match them with notification groups
        $groupedPreviousNotifications = $previouslyFailedLinks ?
            $this->groupLinksByNotificationTargets($previouslyFailedLinks, $notificationType) :
            ['groups' => [], 'owners' => []];

        // Send group notifications (batched)
        foreach ($groupedNotifications['groups'] as $groupId => $links) {
            // Get previously failed links for this notification group
            $previousLinks = isset($groupedPreviousNotifications['groups'][$groupId]) ?
                $groupedPreviousNotifications['groups'][$groupId] : collect();

            $this->sendGroupHealthNotification($groupId, $links, $notificationType, $previousLinks);
        }

        // Send individual owner notifications
        foreach ($groupedNotifications['owners'] as $userId => $links) {
            // Get previously failed links for this owner
            $previousLinks = isset($groupedPreviousNotifications['owners'][$userId]) ?
                $groupedPreviousNotifications['owners'][$userId] : collect();

            $this->sendOwnerHealthNotification($userId, $links, $notificationType, $previousLinks);
        }
    }

    /**
     * Group links by their notification targets (groups and owners)
     */
    private function groupLinksByNotificationTargets(Collection $links, NotificationType $notificationType): array
    {
        $groupedByGroups = [];
        $groupedByOwners = [];

        foreach ($links as $link) {
            // Apply exclusions
            if ($notificationType->exclude_blocked_links && $link->health_status === 'blocked') {
                continue;
            }

            // Check if notification type applies to this link's group
            if (! empty($notificationType->apply_to_link_groups)) {
                if (! in_array($link->group_id, $notificationType->apply_to_link_groups)) {
                    continue;
                }
            }

            // Get notification groups for this link (either specific assignments or defaults)
            $notificationGroups = $this->getNotificationGroupsForLink($link, $notificationType);

            foreach ($notificationGroups as $group) {
                if (! isset($groupedByGroups[$group->id])) {
                    $groupedByGroups[$group->id] = collect();
                }
                $groupedByGroups[$group->id]->push($link);
            }

            // Group by link owner if enabled
            if ($notificationType->notify_link_owner && $link->created_by) {
                if (! isset($groupedByOwners[$link->created_by])) {
                    $groupedByOwners[$link->created_by] = collect();
                }
                $groupedByOwners[$link->created_by]->push($link);
            }
        }

        return [
            'groups' => $groupedByGroups,
            'owners' => $groupedByOwners,
        ];
    }

    /**
     * Get notification groups for a specific link
     */
    private function getNotificationGroupsForLink(Link $link, NotificationType $notificationType): Collection
    {
        // First check for link-specific notification assignments
        $linkSpecificGroups = $link->activeNotifications()
            ->where('notification_type_id', $notificationType->id)
            ->with('notificationGroup')
            ->get()
            ->pluck('notificationGroup')
            ->filter();

        if ($linkSpecificGroups->isNotEmpty()) {
            return $linkSpecificGroups;
        }

        // Fall back to default groups from notification type
        return $notificationType->getDefaultGroups();
    }

    /**
     * Send batched health notification to a group
     */
    private function sendGroupHealthNotification(int $groupId, Collection $links, NotificationType $notificationType, ?Collection $previousLinks = null): void
    {
        $group = NotificationGroup::find($groupId);
        if (! $group || ! $group->is_active) {
            return;
        }

        $targets = $group->getAllTargets();

        $newCount = $links->count();
        $previousCount = $previousLinks ? $previousLinks->count() : 0;
        $totalCount = $newCount + $previousCount;

        $subject = 'Link Health Alert - ';
        if ($newCount > 0 && $previousCount > 0) {
            $subject .= $newCount.' New + '.$previousCount.' Previously Failed Links';
        } elseif ($newCount > 0) {
            $subject .= $newCount.' Link'.($newCount > 1 ? 's' : '').' Failed';
        } else {
            $subject .= $previousCount.' Previously Failed Link'.($previousCount > 1 ? 's' : '');
        }

        $data = [
            'group_name' => $group->name,
            'failed_links' => $links,
            'previously_failed_links' => $previousLinks,
            'total_count' => $totalCount,
            'new_count' => $newCount,
            'previous_count' => $previousCount,
            'notification_type' => $notificationType,
        ];

        foreach ($targets as $target) {
            try {
                match ($target['type']) {
                    'email' => Mail::to($target['target'])->send(
                        new LinkHealthGroupNotification(
                            groupName: $group->name,
                            failedLinks: $links,
                            previouslyFailedLinks: $previousLinks,
                            totalCount: $totalCount,
                            newCount: $newCount,
                            previousCount: $previousCount,
                        )
                    ),
                    'webhook' => $this->sendWebhookNotification($target['target'], $subject, $data),
                    'slack' => $this->sendSlackNotification($target['target'], $subject, $data),
                    'discord' => $this->sendDiscordNotification($target['target'], $subject, $data),
                    'teams' => $this->sendTeamsNotification($target['target'], $subject, $data),
                };

                Log::info('Group health notification sent', [
                    'group_id' => $groupId,
                    'target_type' => $target['type'],
                    'links_count' => $links->count(),
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send group health notification', [
                    'group_id' => $groupId,
                    'target_type' => $target['type'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Send individual health notification to link owner
     */
    private function sendOwnerHealthNotification(int $userId, Collection $links, NotificationType $notificationType, ?Collection $previousLinks = null): void
    {
        $user = User::find($userId);
        if (! $user) {
            return;
        }

        $newCount = $links->count();
        $previousCount = $previousLinks ? $previousLinks->count() : 0;

        if ($newCount > 0 && $previousCount > 0) {
            $subject = 'Your Links Health Alert - '.$newCount.' New + '.$previousCount.' Previously Failed';
        } elseif ($newCount > 0) {
            $subject = 'Your Link'.($newCount > 1 ? 's' : '').' Health Alert';
        } else {
            $subject = 'Your '.$previousCount.' Link'.($previousCount > 1 ? 's' : '').' Still Failed';
        }

        $data = [
            'user' => $user,
            'failed_links' => $links,
            'previously_failed_links' => $previousLinks,
            'total_count' => $newCount + $previousCount,
            'new_count' => $newCount,
            'previous_count' => $previousCount,
            'notification_type' => $notificationType,
        ];

        try {
            Mail::to($user->email)->send(
                new LinkHealthOwnerNotification(
                    user: $user,
                    failedLinks: $links,
                    previouslyFailedLinks: $previousLinks,
                    totalCount: $newCount + $previousCount,
                    newCount: $newCount,
                    previousCount: $previousCount,
                )
            );

            Log::info('Owner health notification sent', [
                'user_id' => $userId,
                'links_count' => $links->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send owner health notification', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send system alert notification
     */
    public function sendSystemAlert(string $message, string $severity = 'high', array $additionalData = []): void
    {
        $notificationType = NotificationType::where('name', 'system_alert')
            ->where('is_active', true)
            ->first();

        if (! $notificationType) {
            return;
        }

        $groups = $notificationType->getDefaultGroups();

        $subject = 'System Alert - '.ucfirst($severity).' Priority';
        $data = array_merge([
            'message' => $message,
            'severity' => $severity,
            'timestamp' => now(),
            'notification_type' => $notificationType,
        ], $additionalData);

        foreach ($groups as $group) {
            $this->sendNotificationToGroup($group, $subject, 'system-alert', $data);
        }
    }

    /**
     * Send maintenance notification
     */
    public function sendMaintenanceNotification(string $message, ?\DateTime $scheduledTime = null, array $additionalData = []): void
    {
        $notificationType = NotificationType::where('name', 'maintenance')
            ->where('is_active', true)
            ->first();

        if (! $notificationType) {
            return;
        }

        $groups = $notificationType->getDefaultGroups();

        $subject = 'Maintenance Notification';
        $data = array_merge([
            'message' => $message,
            'scheduled_time' => $scheduledTime,
            'timestamp' => now(),
            'notification_type' => $notificationType,
        ], $additionalData);

        foreach ($groups as $group) {
            $this->sendNotificationToGroup($group, $subject, 'maintenance', $data);
        }
    }

    /**
     * Send notification to all targets in a group
     */
    private function sendNotificationToGroup(NotificationGroup $group, string $subject, string $template, array $data): void
    {
        if (! $group->is_active) {
            return;
        }

        $targets = $group->getAllTargets();
        $data['group_name'] = $group->name;

        foreach ($targets as $target) {
            try {
                match ($target['type']) {
                    'email' => $this->sendTemplateEmailNotification($target['target'], $template, $data),
                    'webhook' => $this->sendWebhookNotification($target['target'], $subject, $data),
                    'slack' => $this->sendSlackNotification($target['target'], $subject, $data),
                    'discord' => $this->sendDiscordNotification($target['target'], $subject, $data),
                    'teams' => $this->sendTeamsNotification($target['target'], $subject, $data),
                };
            } catch (\Exception $e) {
                Log::error('Failed to send notification to group', [
                    'group_id' => $group->id,
                    'target_type' => $target['type'],
                    'template' => $template,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Send email notification using the appropriate Mailable class
     */
    private function sendTemplateEmailNotification(string $email, string $template, array $data): void
    {
        $mailable = match ($template) {
            'system-alert' => new SystemAlertNotificationMailable(
                message: $data['message'] ?? '',
                severity: $data['severity'] ?? 'medium',
                additionalData: $data,
                groupName: $data['group_name'] ?? '',
            ),
            'maintenance' => new MaintenanceNotificationMailable(
                message: $data['message'] ?? '',
                scheduledTime: $data['scheduled_time'] ?? now(),
                additionalData: $data,
                groupName: $data['group_name'] ?? '',
            ),
            default => null,
        };

        if ($mailable) {
            Mail::to($email)->send($mailable);
        }
    }

    /**
     * Send webhook notification
     */
    private function sendWebhookNotification(array $config, string $subject, array $data): void
    {
        $url = $config['url'];
        $method = $config['method'] ?? 'POST';
        $headers = [];

        if (! empty($config['headers'])) {
            $customHeaders = is_string($config['headers']) ? json_decode($config['headers'], true) : $config['headers'];
            if (is_array($customHeaders)) {
                $headers = $customHeaders;
            }
        }

        $payload = [
            'subject' => $subject,
            'timestamp' => now()->toISOString(),
            'data' => $data,
        ];

        $response = Http::withHeaders($headers)->send($method, $url, [
            'json' => $payload,
        ]);

        if (! $response->successful()) {
            throw new \Exception("Webhook failed with status {$response->status()}");
        }
    }

    /**
     * Send Slack notification
     */
    private function sendSlackNotification(array $config, string $subject, array $data): void
    {
        $webhookUrl = $config['webhook_url'];
        $channel = $config['channel'] ?? null;
        $username = $config['username'] ?? 'Link Monitor';

        $payload = [
            'text' => $subject,
            'username' => $username,
        ];

        if ($channel) {
            $payload['channel'] = $channel;
        }

        // Add rich formatting for different notification types
        if (isset($data['failed_links'])) {
            $payload['attachments'] = [[
                'color' => 'danger',
                'fields' => $data['failed_links']->take(5)->map(function ($link) {
                    return [
                        'title' => $link->original_url,
                        'value' => $link->health_check_message ?? 'Health check failed',
                        'short' => false,
                    ];
                })->toArray(),
            ]];
        }

        $response = Http::post($webhookUrl, $payload);

        if (! $response->successful()) {
            throw new \Exception("Slack notification failed with status {$response->status()}");
        }
    }

    /**
     * Send Discord notification
     */
    private function sendDiscordNotification(array $config, string $subject, array $data): void
    {
        $webhookUrl = $config['webhook_url'];
        $username = $config['username'] ?? 'Link Monitor';

        $payload = [
            'content' => $subject,
            'username' => $username,
        ];

        // Add embed for rich formatting
        if (isset($data['failed_links'])) {
            $payload['embeds'] = [[
                'title' => $subject,
                'color' => 0xFF0000, // Red
                'fields' => $data['failed_links']->take(5)->map(function ($link) {
                    return [
                        'name' => $link->original_url,
                        'value' => $link->health_check_message ?? 'Health check failed',
                    ];
                })->toArray(),
                'timestamp' => now()->toISOString(),
            ]];
        }

        $response = Http::post($webhookUrl, $payload);

        if (! $response->successful()) {
            throw new \Exception("Discord notification failed with status {$response->status()}");
        }
    }

    /**
     * Send Teams notification
     */
    private function sendTeamsNotification(array $config, string $subject, array $data): void
    {
        $webhookUrl = $config['webhook_url'];

        $payload = [
            '@type' => 'MessageCard',
            '@context' => 'https://schema.org/extensions',
            'summary' => $subject,
            'themeColor' => '0078D4',
            'sections' => [[
                'activityTitle' => $subject,
                'activitySubtitle' => 'Link Health Monitor',
                'facts' => [],
            ]],
        ];

        // Add facts for failed links
        if (isset($data['failed_links'])) {
            $payload['sections'][0]['facts'] = $data['failed_links']->take(5)->map(function ($link) {
                return [
                    'name' => 'Failed Link',
                    'value' => $link->original_url.' - '.($link->health_check_message ?? 'Health check failed'),
                ];
            })->toArray();
        }

        $response = Http::post($webhookUrl, $payload);

        if (! $response->successful()) {
            throw new \Exception("Teams notification failed with status {$response->status()}");
        }
    }
}
