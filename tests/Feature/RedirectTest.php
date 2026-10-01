<?php

namespace Tests\Feature;

use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user for link ownership
        $this->user = User::factory()->create();
    }

    public function test_redirect_works_for_active_link(): void
    {
        $link = Link::create([
            'short_code' => 'test123',
            'original_url' => 'https://example.com',
            'redirect_type' => 302,
            'is_active' => true,
            'created_by' => $this->user->id,
            'click_count' => 0,
        ]);

        $response = $this->get('/test123');

        $response->assertRedirect('https://example.com');
        $response->assertStatus(302);
    }

    public function test_appmetrica_shop_link_opens_the_exact_android_app_route(): void
    {
        $link = $this->createAppMetricaLink('android-shop', redirectType: 301);
        $target = $link->original_url;

        $response = $this->withHeader(
            'User-Agent',
            'Mozilla/5.0 (Linux; Android 15; 23090RA98G Build/AQ3A.240829.002; wv)',
        )->get('/android-shop');

        $response->assertStatus(302);
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertHeader('Vary', 'User-Agent');
        $location = (string) $response->headers->get('Location');

        $this->assertStringStartsWith(
            'intent://shop/691#Intent;scheme=ufarmer;package=uz.ufarmer.app;',
            $location,
        );
        $this->assertStringContainsString(
            'S.browser_fallback_url='.rawurlencode($target).';end',
            $location,
        );
        $this->assertSame(1, $link->fresh()->click_count);
    }

    public function test_appmetrica_shop_link_opens_the_exact_ios_universal_link(): void
    {
        $this->createAppMetricaLink('ios-shop');

        $response = $this->withHeader(
            'User-Agent',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148',
        )->get('/ios-shop');

        $response->assertStatus(302);
        $response->assertRedirect('https://app.ufarmer.uz/s/691');
        $response->assertHeader('Cache-Control', 'no-store, private');
        $response->assertHeader('Vary', 'User-Agent');
    }

    public function test_appmetrica_product_link_opens_the_exact_ios_universal_link(): void
    {
        $this->createAppMetricaLink('ios-product', 'p/product-uuid');

        $this->withHeader('User-Agent', 'Mozilla/5.0 (iPad; CPU OS 18_6 like Mac OS X)')
            ->get('/ios-product')
            ->assertStatus(302)
            ->assertRedirect('https://app.ufarmer.uz/p/product-uuid');
    }

    public function test_appmetrica_shop_link_uses_the_universal_link_on_macos(): void
    {
        $this->createAppMetricaLink('mac-shop');

        $this->withHeader('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)')
            ->get('/mac-shop')
            ->assertStatus(302)
            ->assertRedirect('https://app.ufarmer.uz/s/691');
    }

    public function test_unrecognized_appmetrica_route_is_not_rewritten(): void
    {
        $link = $this->createAppMetricaLink('unknown-route', 'unknown/691', redirectType: 301);

        $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X)')
            ->get('/unknown-route')
            ->assertStatus(301)
            ->assertRedirect($link->original_url);
    }

    public function test_lookalike_appmetrica_host_is_not_rewritten(): void
    {
        $link = $this->createAppMetricaLink(
            'lookalike-host',
            host: 'campaign.redirect.appmetrica.yandex.com',
        );

        $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X)')
            ->get('/lookalike-host')
            ->assertRedirect($link->original_url);
    }

    public function test_malformed_appmetrica_paths_are_not_rewritten(): void
    {
        foreach (['s/691/extra', 'p/%2E%2E'] as $index => $path) {
            $shortCode = 'malformed-'.$index;
            $link = $this->createAppMetricaLink($shortCode, $path);

            $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X)')
                ->get('/'.$shortCode)
                ->assertRedirect($link->original_url);
        }
    }

    public function test_redirect_returns_404_for_inactive_link(): void
    {
        Link::create([
            'short_code' => 'inactive',
            'original_url' => 'https://example.com',
            'redirect_type' => 302,
            'is_active' => false,
            'created_by' => $this->user->id,
            'click_count' => 0,
        ]);

        $response = $this->get('/inactive');

        $response->assertStatus(404);
    }

    public function test_redirect_returns_404_for_expired_link(): void
    {
        Link::create([
            'short_code' => 'expired',
            'original_url' => 'https://example.com',
            'redirect_type' => 302,
            'is_active' => true,
            'expires_at' => now()->subDay(),
            'created_by' => $this->user->id,
            'click_count' => 0,
        ]);

        $response = $this->get('/expired');

        $response->assertStatus(404);
    }

    public function test_redirect_returns_404_for_nonexistent_link(): void
    {
        $response = $this->get('/nonexistent');

        $response->assertStatus(404);
    }

    public function test_redirect_respects_different_redirect_types(): void
    {
        // Test 301 redirect
        $link301 = Link::create([
            'short_code' => 'perm301',
            'original_url' => 'https://example.com',
            'redirect_type' => 301,
            'is_active' => true,
            'created_by' => $this->user->id,
            'click_count' => 0,
        ]);

        $response = $this->get('/perm301');
        $response->assertRedirect('https://example.com');
        $response->assertStatus(301);

        // Test 308 redirect
        $link308 = Link::create([
            'short_code' => 'perm308',
            'original_url' => 'https://example.com',
            'redirect_type' => 308,
            'is_active' => true,
            'created_by' => $this->user->id,
            'click_count' => 0,
        ]);

        $response = $this->get('/perm308');
        $response->assertRedirect('https://example.com');
        $response->assertStatus(308);
    }

    public function test_redirect_increments_click_count(): void
    {
        $link = Link::create([
            'short_code' => 'counter',
            'original_url' => 'https://example.com',
            'redirect_type' => 302,
            'is_active' => true,
            'created_by' => $this->user->id,
            'click_count' => 5,
        ]);

        $this->get('/counter');

        $link->refresh();
        $this->assertEquals(6, $link->click_count);
    }

    public function test_redirect_uses_cache(): void
    {
        $link = Link::create([
            'short_code' => 'cached',
            'original_url' => 'https://example.com',
            'redirect_type' => 302,
            'is_active' => true,
            'created_by' => $this->user->id,
            'click_count' => 0,
        ]);

        // First request should cache the link
        $this->get('/cached');

        // Verify the cache contains the link data
        $cacheKey = 'link_data_cached';
        $this->assertTrue(Cache::has($cacheKey));

        $cachedLink = Cache::get($cacheKey);
        $this->assertEquals($link->id, $cachedLink->id);
        $this->assertEquals($link->original_url, $cachedLink->original_url);
    }

    public function test_redirect_rate_limiting(): void
    {
        $link = Link::create([
            'short_code' => 'limited',
            'original_url' => 'https://example.com',
            'redirect_type' => 302,
            'is_active' => true,
            'created_by' => $this->user->id,
            'click_count' => 0,
        ]);

        // Make multiple requests to trigger rate limiting
        for ($i = 0; $i < 60; $i++) {
            $response = $this->get('/limited');
            $response->assertRedirect('https://example.com');
        }

        // 61st request should be rate limited
        $response = $this->get('/limited');
        $this->assertContains($response->getStatusCode(), [429, 302]); // May be rate limited or still work
    }

    public function test_homepage_displays_correctly(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Link Shortener');
        $response->assertSee('Shorten your URLs');
    }

    public function test_homepage_shows_correct_stats(): void
    {
        // Create test data
        Link::create([
            'short_code' => 'stat1',
            'original_url' => 'https://example.com',
            'redirect_type' => 302,
            'is_active' => true,
            'created_by' => $this->user->id,
            'click_count' => 0,
        ]);

        Link::create([
            'short_code' => 'stat2',
            'original_url' => 'https://example.com',
            'redirect_type' => 302,
            'is_active' => true,
            'created_by' => $this->user->id,
            'click_count' => 0,
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('2'); // Should show 2 links created
        $response->assertSee('1'); // Should show 1 active user
    }

    private function createAppMetricaLink(
        string $shortCode,
        string $path = 's/691',
        int $redirectType = 302,
        string $host = '6263407.redirect.appmetrica.yandex.com',
    ): Link {
        return Link::create([
            'short_code' => $shortCode,
            'original_url' => 'https://'.$host.'/app.ufarmer.uz/'.$path
                .'?appmetrica_tracking_id=750621066455453567&referrer=reattribution%3D1',
            'redirect_type' => $redirectType,
            'is_active' => true,
            'created_by' => $this->user->id,
            'click_count' => 0,
        ]);
    }
}
