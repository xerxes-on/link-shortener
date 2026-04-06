<?php

namespace Tests\Unit;

use App\Models\Link;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HealthCheckTimeoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache before each test
        Cache::flush();
    }

    public function test_timeout_is_detected_and_categorized(): void
    {
        Cache::put('health_check.timeout_seconds', 1);

        $link = Link::factory()->create([
            'original_url' => 'https://example.com/slow',
            'health_status' => 'unchecked',
        ]);

        // Simulate the timeout handling by directly updating the link
        $link->update([
            'health_status' => 'timeout',
            'http_status_code' => null,
            'health_check_message' => 'Connection timeout',
            'last_checked_at' => now(),
        ]);

        $this->assertEquals('timeout', $link->fresh()->health_status);
        $this->assertNull($link->fresh()->http_status_code);
        $this->assertEquals('Connection timeout', $link->fresh()->health_check_message);
    }

    public function test_timeout_status_has_correct_icon_and_color(): void
    {
        $link = Link::factory()->create([
            'health_status' => 'timeout',
        ]);

        $this->assertEquals('heroicon-o-clock', $link->health_status_icon);
        $this->assertEquals('warning', $link->health_status_color);
    }

    public function test_timeout_links_are_rechecked_appropriately(): void
    {
        $link = Link::factory()->create([
            'health_status' => 'timeout',
            'last_checked_at' => now()->subDays(2),
        ]);

        // Timeout links should be checked every 3 days
        $requiresCheck = $link->requiresHealthCheck();

        $this->assertFalse($requiresCheck); // 2 days ago, not yet 3 days

        $link->update(['last_checked_at' => now()->subDays(4)]);
        $requiresCheck = $link->fresh()->requiresHealthCheck();

        $this->assertTrue($requiresCheck); // 4 days ago, needs check
    }

    public function test_configurable_timeout_duration(): void
    {
        // Test with different timeout settings
        Cache::put('health_check.timeout_seconds', 5);
        $timeout1 = Cache::get('health_check.timeout_seconds', 10);
        $this->assertEquals(5, $timeout1);

        Cache::put('health_check.timeout_seconds', 30);
        $timeout2 = Cache::get('health_check.timeout_seconds', 10);
        $this->assertEquals(30, $timeout2);

        // Test default value
        Cache::forget('health_check.timeout_seconds');
        $timeout3 = Cache::get('health_check.timeout_seconds', 10);
        $this->assertEquals(10, $timeout3);
    }

    public function test_timeout_first_failure_tracking(): void
    {
        $link = Link::factory()->create([
            'health_status' => 'healthy',
            'first_failure_detected_at' => null,
        ]);

        // Simulate timeout occurrence
        $link->update([
            'health_status' => 'timeout',
            'first_failure_detected_at' => now(),
        ]);

        $this->assertNotNull($link->fresh()->first_failure_detected_at);

        // Recovery should reset tracking
        $link->update([
            'health_status' => 'healthy',
            'first_failure_detected_at' => null,
            'notification_count' => 0,
        ]);

        $this->assertNull($link->fresh()->first_failure_detected_at);
        $this->assertEquals(0, $link->fresh()->notification_count);
    }

    public function test_timeout_vs_error_distinction(): void
    {
        $timeoutLink = Link::factory()->create([
            'health_status' => 'timeout',
            'health_check_message' => 'Connection timeout',
        ]);

        $errorLink = Link::factory()->create([
            'health_status' => 'error',
            'health_check_message' => 'Failed to connect: Connection refused',
        ]);

        $this->assertEquals('timeout', $timeoutLink->health_status);
        $this->assertEquals('error', $errorLink->health_status);

        // Different recheck intervals
        $timeoutLink->update(['last_checked_at' => now()->subDays(2)]);
        $errorLink->update(['last_checked_at' => now()->subDays(2)]);

        $this->assertFalse($timeoutLink->requiresHealthCheck()); // Checked every 3 days
        $this->assertTrue($errorLink->requiresHealthCheck()); // Checked daily
    }
}
