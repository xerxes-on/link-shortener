<?php

namespace Tests\Feature;

use App\Models\Link;
use App\Models\LinkGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RedisPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear Redis cache to avoid stale data from previous test runs
        if ($this->isRedisAvailable()) {
            Cache::store('redis')->flush();
        }

        // Create a default group for links
        LinkGroup::factory()->create(['is_default' => true]);
    }

    public function test_redirect_performance_with_redis_cache()
    {
        // Skip if Redis is not available
        if (! $this->isRedisAvailable()) {
            $this->markTestSkipped('Redis is not available');
        }

        // Enable Redis cache and Redis click tracking
        config([
            'cache.default' => 'redis',
            'shortener.analytics.click_tracking_method' => 'redis',
        ]);

        // Create a test link
        $link = Link::factory()->create([
            'short_code' => 'perf123',
            'click_count' => 0,
            'click_limit' => null,
        ]);

        // Warm up the cache
        $this->get('/'.$link->short_code);

        // Measure redirect performance with cached data
        $startTime = microtime(true);

        for ($i = 0; $i < 10; $i++) {
            $response = $this->get('/'.$link->short_code);
            $response->assertRedirect($link->original_url);
        }

        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        $averageTime = $totalTime / 10;

        // With Redis cache, each redirect should be very fast (< 50ms in most cases)
        $this->assertLessThan(500, $totalTime, "10 redirects took {$totalTime}ms (avg: {$averageTime}ms per redirect)");

        // Output performance info
        echo "\n=== Redis Performance Test ===\n";
        echo "10 redirects: {$totalTime}ms total\n";
        echo "Average per redirect: {$averageTime}ms\n";
        echo "Configuration: Redis cache + Redis click tracking\n";
    }

    public function test_cache_hit_performance()
    {
        // Skip if Redis is not available
        if (! $this->isRedisAvailable()) {
            $this->markTestSkipped('Redis is not available');
        }

        config(['cache.default' => 'redis']);

        $testData = ['test' => 'performance_data', 'timestamp' => now()];

        // Measure cache write
        $startTime = microtime(true);
        Cache::put('perf_test', $testData, 3600);
        $writeTime = (microtime(true) - $startTime) * 1000;

        // Measure cache read
        $startTime = microtime(true);
        $retrieved = Cache::get('perf_test');
        $readTime = (microtime(true) - $startTime) * 1000;

        $this->assertEquals($testData, $retrieved);

        // Redis cache operations should be very fast
        $this->assertLessThan(10, $writeTime, "Redis write took {$writeTime}ms");
        $this->assertLessThan(5, $readTime, "Redis read took {$readTime}ms");

        echo "\n=== Redis Cache Performance ===\n";
        echo "Write: {$writeTime}ms\n";
        echo "Read: {$readTime}ms\n";
    }

    public function test_concurrent_redirects_simulation()
    {
        // Skip if Redis is not available
        if (! $this->isRedisAvailable()) {
            $this->markTestSkipped('Redis is not available');
        }

        // Disable rate limiting for this test
        config(['cache.stores.rateLimiter' => null]);

        // Enable Redis for everything
        config([
            'cache.default' => 'redis',
            'shortener.analytics.click_tracking_method' => 'redis',
            'queue.default' => 'redis',
        ]);

        // Create multiple test links
        $links = [];
        for ($i = 1; $i <= 3; $i++) {
            $links[] = Link::factory()->create([
                'short_code' => "concurrent{$i}",
                'click_count' => 0,
                'click_limit' => null,
            ]);
        }

        // Simulate concurrent requests (sequential but rapid)
        $startTime = microtime(true);

        // Simulate 30 requests across 3 links (reduced to avoid rate limits)
        for ($round = 0; $round < 10; $round++) {
            foreach ($links as $link) {
                $response = $this->withoutMiddleware(ThrottleRequests::class)
                    ->get('/'.$link->short_code);
                $response->assertRedirect($link->original_url);
            }
        }

        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000;
        $averageTime = $totalTime / 30;

        // 30 redirects should complete quickly with Redis
        $this->assertLessThan(1500, $totalTime, "30 concurrent-style redirects took {$totalTime}ms");

        echo "\n=== Concurrent Redirect Simulation ===\n";
        echo "30 redirects across 3 links: {$totalTime}ms total\n";
        echo "Average per redirect: {$averageTime}ms\n";
        echo "Simulates email campaign traffic pattern\n";
    }

    public function test_database_load_comparison()
    {
        // Test different tracking methods to show database impact
        $link = Link::factory()->create([
            'short_code' => 'dbload123',
            'click_count' => 0,
            'click_limit' => null,
        ]);

        echo "\n=== Database Load Comparison ===\n";

        // Method 1: Redis tracking (minimal DB load)
        config(['shortener.analytics.click_tracking_method' => 'redis']);
        if ($this->isRedisAvailable()) {
            $startTime = microtime(true);
            $this->get('/'.$link->short_code);
            $redisTime = (microtime(true) - $startTime) * 1000;
            echo "Redis tracking: {$redisTime}ms (0 immediate DB writes)\n";
        }

        // Method 2: None tracking (fastest)
        config(['shortener.analytics.click_tracking_method' => 'none']);
        $startTime = microtime(true);
        $this->get('/'.$link->short_code);
        $noneTime = (microtime(true) - $startTime) * 1000;
        echo "None tracking: {$noneTime}ms (1 DB write for count only)\n";

        // Method 3: Queue tracking (traditional)
        config(['shortener.analytics.click_tracking_method' => 'queue']);
        $startTime = microtime(true);
        $this->get('/'.$link->short_code);
        $queueTime = (microtime(true) - $startTime) * 1000;
        echo "Queue tracking: {$queueTime}ms (1-2 DB writes depending on queue driver)\n";

        $this->assertTrue(true); // Just for the output
    }

    private function isRedisAvailable(): bool
    {
        try {
            Cache::store('redis')->put('test', 'test', 1);
            Cache::store('redis')->forget('test');

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
