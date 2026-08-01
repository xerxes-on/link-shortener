<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Click;
use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ApiStatsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Frozen "now" for every test: a Monday, so start-of-week and start-of-month
     * land on predictable dates.
     */
    private const NOW = '2026-06-15 12:00:00';

    protected User $user;

    protected Link $link;

    protected string $plainTextKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse(self::NOW));

        $this->user = User::factory()->create();

        $this->plainTextKey = 'sk_'.str_repeat('test123', 5).'abcdef';
        ApiKey::create([
            'name' => 'Test API Key',
            'key_hash' => hash('sha256', $this->plainTextKey),
            'permissions' => ['links:read', 'stats:read'],
            'created_by' => $this->user->id,
        ]);

        $this->link = Link::factory()->create([
            'short_code' => 'my-link',
            'original_url' => 'https://example.com/target',
            'created_by' => $this->user->id,
            'click_count' => 6,
        ]);
    }

    private function stats(array $query = []): TestResponse
    {
        return $this->getJson(
            '/api/links/'.$this->link->id.'/stats'.($query ? '?'.http_build_query($query) : ''),
            ['Authorization' => 'Bearer '.$this->plainTextKey]
        );
    }

    private function click(array $attributes = []): Click
    {
        return Click::factory()->create(array_merge([
            'link_id' => $this->link->id,
            'clicked_at' => now(),
            'referer' => null,
            'country' => null,
            'city' => null,
            'utm_source' => null,
            'utm_medium' => null,
            'utm_campaign' => null,
        ], $attributes));
    }

    public function test_stats_returns_the_documented_shape(): void
    {
        $this->stats()
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'link' => ['id', 'short_code', 'original_url', 'click_count', 'is_active', 'created_at'],
                    'short_url',
                    'stats' => [
                        'total_clicks',
                        'today_clicks',
                        'this_week_clicks',
                        'this_month_clicks',
                        'daily_series' => ['*' => ['date', 'clicks']],
                        'top_countries',
                        'top_referrers',
                        'utm' => ['campaigns', 'sources', 'mediums'],
                    ],
                ],
            ]);
    }

    public function test_stats_counts_totals_for_each_window(): void
    {
        // 3 today (Mon 15 Jun), 2 on Sat 13 Jun (this month, previous week),
        // 1 on 26 May (previous month)
        $this->click(['clicked_at' => now()]);
        $this->click(['clicked_at' => now()->copy()->subHours(2)]);
        $this->click(['clicked_at' => now()->copy()->startOfDay()]);
        $this->click(['clicked_at' => Carbon::parse('2026-06-13 10:00:00')]);
        $this->click(['clicked_at' => Carbon::parse('2026-06-13 11:00:00')]);
        $this->click(['clicked_at' => Carbon::parse('2026-05-26 10:00:00')]);

        $this->stats()->assertStatus(200)->assertJson([
            'data' => [
                'stats' => [
                    'total_clicks' => 6,
                    'today_clicks' => 3,
                    'this_week_clicks' => 3,
                    'this_month_clicks' => 5,
                ],
            ],
        ]);
    }

    public function test_daily_series_is_zero_filled_and_honours_the_days_parameter(): void
    {
        $this->click(['clicked_at' => now()]);
        $this->click(['clicked_at' => now()]);
        $this->click(['clicked_at' => Carbon::parse('2026-06-13 10:00:00')]);

        $series = $this->stats(['days' => 7])->assertStatus(200)->json('data.stats.daily_series');

        $this->assertCount(7, $series);
        $this->assertSame('2026-06-09', $series[0]['date']);
        $this->assertSame('2026-06-15', $series[6]['date']);

        $byDate = collect($series)->pluck('clicks', 'date');
        $this->assertSame(2, $byDate['2026-06-15']);
        $this->assertSame(1, $byDate['2026-06-13']);
        $this->assertSame(0, $byDate['2026-06-14']);
        $this->assertSame(0, $byDate['2026-06-09']);
    }

    public function test_daily_series_defaults_to_30_days_and_clamps_to_365(): void
    {
        $this->assertCount(30, $this->stats()->json('data.stats.daily_series'));
        $this->assertCount(365, $this->stats(['days' => 5000])->json('data.stats.daily_series'));
        $this->assertCount(1, $this->stats(['days' => 0])->json('data.stats.daily_series'));
    }

    public function test_daily_series_excludes_clicks_outside_the_window(): void
    {
        $this->click(['clicked_at' => Carbon::parse('2026-06-01 10:00:00')]);

        $series = collect($this->stats(['days' => 7])->json('data.stats.daily_series'));

        $this->assertSame(0, $series->sum('clicks'));
    }

    public function test_top_countries_are_ordered_by_click_count(): void
    {
        $this->click(['country' => 'US']);
        $this->click(['country' => 'US']);
        $this->click(['country' => 'US']);
        $this->click(['country' => 'GB']);
        $this->click(['country' => 'GB']);
        $this->click(['country' => 'FR']);
        $this->click(['country' => null]);

        $this->assertSame([
            ['country' => 'US', 'clicks' => 3],
            ['country' => 'GB', 'clicks' => 2],
            ['country' => 'FR', 'clicks' => 1],
        ], $this->stats()->json('data.stats.top_countries'));
    }

    public function test_top_referrers_are_grouped_by_host(): void
    {
        // Same host, different paths / schemes / www - must collapse into one bucket
        $this->click(['referer' => 'https://www.google.com/search?q=one']);
        $this->click(['referer' => 'https://google.com/search?q=two']);
        $this->click(['referer' => 'http://google.com/']);
        $this->click(['referer' => 'https://GOOGLE.com/search#frag']);
        $this->click(['referer' => 'https://news.ycombinator.com/item?id=1']);
        $this->click(['referer' => 'https://news.ycombinator.com/']);
        $this->click(['referer' => 'https://example.org:8080/path']);
        $this->click(['referer' => null]);
        $this->click(['referer' => null]);
        $this->click(['referer' => null]);
        $this->click(['referer' => null]);
        $this->click(['referer' => null]);

        $this->assertSame([
            ['referrer' => null, 'clicks' => 5],
            ['referrer' => 'google.com', 'clicks' => 4],
            ['referrer' => 'news.ycombinator.com', 'clicks' => 2],
            ['referrer' => 'example.org', 'clicks' => 1],
        ], $this->stats()->json('data.stats.top_referrers'));
    }

    public function test_top_breakdowns_are_limited_to_ten_rows(): void
    {
        foreach (range(1, 12) as $index) {
            // Give each country a distinct count so ordering is unambiguous
            Click::factory()->count($index)->create([
                'link_id' => $this->link->id,
                'clicked_at' => now(),
                'country' => 'C'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            ]);
        }

        $countries = $this->stats()->json('data.stats.top_countries');

        $this->assertCount(10, $countries);
        $this->assertSame('C12', $countries[0]['country']);
        $this->assertSame(12, $countries[0]['clicks']);
        $this->assertSame('C03', $countries[9]['country']);
    }

    public function test_utm_breakdowns_exclude_nulls(): void
    {
        $this->click(['utm_campaign' => 'spring2026', 'utm_source' => 'newsletter', 'utm_medium' => 'email']);
        $this->click(['utm_campaign' => 'spring2026', 'utm_source' => 'newsletter', 'utm_medium' => 'email']);
        $this->click(['utm_campaign' => 'autumn2026', 'utm_source' => 'twitter', 'utm_medium' => 'social']);
        $this->click();

        $utm = $this->stats()->json('data.stats.utm');

        $this->assertSame([
            ['value' => 'spring2026', 'clicks' => 2],
            ['value' => 'autumn2026', 'clicks' => 1],
        ], $utm['campaigns']);

        $this->assertSame([
            ['value' => 'newsletter', 'clicks' => 2],
            ['value' => 'twitter', 'clicks' => 1],
        ], $utm['sources']);

        $this->assertSame([
            ['value' => 'email', 'clicks' => 2],
            ['value' => 'social', 'clicks' => 1],
        ], $utm['mediums']);
    }

    public function test_stats_only_count_clicks_for_the_requested_link(): void
    {
        $otherLink = Link::factory()->create(['created_by' => $this->user->id]);

        $this->click();
        Click::factory()->count(4)->create([
            'link_id' => $otherLink->id,
            'clicked_at' => now(),
        ]);

        $this->stats()->assertJson(['data' => ['stats' => ['total_clicks' => 1]]]);
    }

    public function test_stats_for_another_users_link_are_not_found(): void
    {
        $otherUser = User::factory()->create();
        $foreignLink = Link::factory()->create(['created_by' => $otherUser->id]);

        $this->getJson('/api/links/'.$foreignLink->id.'/stats', [
            'Authorization' => 'Bearer '.$this->plainTextKey,
        ])->assertStatus(404)->assertJson(['error' => 'Not found']);
    }

    public function test_stats_require_the_stats_read_permission(): void
    {
        $readOnlyKey = 'sk_'.str_repeat('readonly', 5).'abcdef';
        ApiKey::create([
            'name' => 'Read Only Key',
            'key_hash' => hash('sha256', $readOnlyKey),
            'permissions' => ['links:read'],
            'created_by' => $this->user->id,
        ]);

        $this->getJson('/api/links/'.$this->link->id.'/stats', [
            'Authorization' => 'Bearer '.$readOnlyKey,
        ])->assertStatus(403);
    }

    public function test_stats_cost_does_not_grow_with_the_number_of_clicks(): void
    {
        Click::factory()->count(250)->create([
            'link_id' => $this->link->id,
            'clicked_at' => now(),
        ]);

        DB::enableQueryLog();
        $this->stats()->assertStatus(200);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Aggregation happens in SQL: a fixed handful of queries regardless of click volume
        $this->assertLessThan(15, count($queries));

        foreach ($queries as $query) {
            $this->assertStringNotContainsString(
                'select * from "clicks"',
                strtolower($query['query']),
                'Stats must never hydrate the full click collection'
            );
        }
    }
}
