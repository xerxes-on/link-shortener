<?php

namespace App\Services;

use App\Models\Link;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Builds click statistics for a single link.
 *
 * Every figure is aggregated in SQL — the click rows are never hydrated, so the
 * cost is independent of how many clicks a link has accumulated.
 */
class LinkStatsService
{
    /**
     * Upper bound for the `days` window of the daily series.
     */
    public const MAX_DAYS = 365;

    /**
     * Default `days` window of the daily series.
     */
    public const DEFAULT_DAYS = 30;

    /**
     * How many rows each "top" breakdown returns.
     */
    public const TOP_LIMIT = 10;

    /**
     * @return array{
     *     total_clicks: int,
     *     today_clicks: int,
     *     this_week_clicks: int,
     *     this_month_clicks: int,
     *     daily_series: list<array{date: string, clicks: int}>,
     *     top_countries: list<array{country: string, clicks: int}>,
     *     top_referrers: list<array{referrer: string|null, clicks: int}>,
     *     utm: array{
     *         campaigns: list<array{value: string, clicks: int}>,
     *         sources: list<array{value: string, clicks: int}>,
     *         mediums: list<array{value: string, clicks: int}>
     *     }
     * }
     */
    public function forLink(Link $link, int $days = self::DEFAULT_DAYS): array
    {
        $days = max(1, min($days, self::MAX_DAYS));

        return [
            ...$this->totals($link),
            'daily_series' => $this->dailySeries($link, $days),
            'top_countries' => $this->topCountries($link),
            'top_referrers' => $this->topReferrers($link),
            'utm' => [
                'campaigns' => $this->topValues($link, 'utm_campaign'),
                'sources' => $this->topValues($link, 'utm_source'),
                'mediums' => $this->topValues($link, 'utm_medium'),
            ],
        ];
    }

    /**
     * Total, today, this-week and this-month counts in a single pass.
     *
     * @return array{total_clicks: int, today_clicks: int, this_week_clicks: int, this_month_clicks: int}
     */
    private function totals(Link $link): array
    {
        $row = $this->clicks($link)
            ->selectRaw('COUNT(*) as total_clicks')
            ->selectRaw('SUM(CASE WHEN clicked_at >= ? THEN 1 ELSE 0 END) as today_clicks', [now()->startOfDay()])
            ->selectRaw('SUM(CASE WHEN clicked_at >= ? THEN 1 ELSE 0 END) as this_week_clicks', [now()->startOfWeek()])
            ->selectRaw('SUM(CASE WHEN clicked_at >= ? THEN 1 ELSE 0 END) as this_month_clicks', [now()->startOfMonth()])
            ->first();

        return [
            'total_clicks' => (int) $row->total_clicks,
            'today_clicks' => (int) $row->today_clicks,
            'this_week_clicks' => (int) $row->this_week_clicks,
            'this_month_clicks' => (int) $row->this_month_clicks,
        ];
    }

    /**
     * One entry per day for the last $days days, including days with no clicks.
     *
     * @return list<array{date: string, clicks: int}>
     */
    private function dailySeries(Link $link, int $days): array
    {
        $start = now()->subDays($days - 1)->startOfDay();
        $end = now()->startOfDay();

        $counts = $this->clicks($link)
            ->where('clicked_at', '>=', $start)
            ->selectRaw($this->dateExpression().' as date, COUNT(*) as clicks')
            ->groupByRaw($this->dateExpression())
            ->pluck('clicks', 'date');

        $series = [];

        for ($date = $start->copy(); $date <= $end; $date->addDay()) {
            $key = $date->format('Y-m-d');

            $series[] = [
                'date' => $key,
                'clicks' => (int) $counts->get($key, 0),
            ];
        }

        return $series;
    }

    /**
     * @return list<array{country: string, clicks: int}>
     */
    private function topCountries(Link $link): array
    {
        return $this->clicks($link)
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->selectRaw('country, COUNT(*) as clicks')
            ->groupBy('country')
            ->orderByRaw('COUNT(*) DESC')
            ->orderBy('country')
            ->limit(self::TOP_LIMIT)
            ->get()
            ->map(fn (object $row): array => [
                'country' => $row->country,
                'clicks' => (int) $row->clicks,
            ])
            ->all();
    }

    /**
     * Referrers grouped by host, so every path on a domain counts towards that domain.
     *
     * The `null` bucket is direct traffic (no referer recorded).
     *
     * @return list<array{referrer: string|null, clicks: int}>
     */
    private function topReferrers(Link $link): array
    {
        $host = $this->referrerHostExpression();

        return $this->clicks($link)
            ->selectRaw("{$host} as referrer, COUNT(*) as clicks")
            ->groupByRaw($host)
            ->orderByRaw('COUNT(*) DESC')
            ->orderByRaw("{$host}")
            ->limit(self::TOP_LIMIT)
            ->get()
            ->map(fn (object $row): array => [
                'referrer' => ($row->referrer === null || $row->referrer === '') ? null : $row->referrer,
                'clicks' => (int) $row->clicks,
            ])
            ->all();
    }

    /**
     * Top values of a single UTM column, nulls excluded.
     *
     * @param  'utm_campaign'|'utm_source'|'utm_medium'  $column
     * @return list<array{value: string, clicks: int}>
     */
    private function topValues(Link $link, string $column): array
    {
        return $this->clicks($link)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->selectRaw("{$column} as value, COUNT(*) as clicks")
            ->groupBy($column)
            ->orderByRaw('COUNT(*) DESC')
            ->orderBy($column)
            ->limit(self::TOP_LIMIT)
            ->get()
            ->map(fn (object $row): array => [
                'value' => $row->value,
                'clicks' => (int) $row->clicks,
            ])
            ->all();
    }

    private function clicks(Link $link): Builder
    {
        return DB::table('clicks')->where('link_id', $link->id);
    }

    /**
     * Database-agnostic `Y-m-d` truncation of `clicked_at`.
     */
    private function dateExpression(): string
    {
        return $this->isSqlite()
            ? "strftime('%Y-%m-%d', clicked_at)"
            : "DATE_FORMAT(clicked_at, '%Y-%m-%d')";
    }

    /**
     * SQL that reduces the stored `referer` URL to a bare host (no scheme, port, path or `www.`).
     *
     * Grouping has to happen on the host itself: grouping on the raw URL first and
     * normalising afterwards would split one domain across many rows and let the real
     * top hosts fall outside the LIMIT.
     */
    private function referrerHostExpression(): string
    {
        $withoutScheme = "REPLACE(REPLACE(LOWER(referer), 'https://', ''), 'http://', '')";

        if ($this->isSqlite()) {
            // SQLite has no SUBSTRING_INDEX. Every host terminator is mapped to '/' in a
            // throwaway copy so a single INSTR() finds the first one; because REPLACE is
            // character-for-character here, that offset still applies to the original string.
            $terminators = "REPLACE(REPLACE(REPLACE({$withoutScheme}, '?', '/'), '#', '/'), ':', '/')";
            $host = "SUBSTR({$withoutScheme}, 1, INSTR({$terminators} || '/', '/') - 1)";
        } else {
            $host = "SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX({$withoutScheme}, '/', 1), '?', 1), '#', 1), ':', 1)";
        }

        return "CASE WHEN {$host} LIKE 'www.%' THEN SUBSTR({$host}, 5) ELSE {$host} END";
    }

    private function isSqlite(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }
}
