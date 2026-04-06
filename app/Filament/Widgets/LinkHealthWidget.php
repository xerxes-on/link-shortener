<?php

namespace App\Filament\Widgets;

use App\Models\Link;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LinkHealthWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        // Get health status counts for ACTIVE links only
        $healthCounts = Link::selectRaw('health_status, COUNT(*) as count')
            ->where('is_active', true)
            ->groupBy('health_status')
            ->pluck('count', 'health_status')
            ->toArray();

        $healthy = $healthCounts['healthy'] ?? 0;
        $warning = $healthCounts['warning'] ?? 0;
        $error = $healthCounts['error'] ?? 0;
        $blocked = $healthCounts['blocked'] ?? 0;
        $timeout = $healthCounts['timeout'] ?? 0;
        $unchecked = $healthCounts['unchecked'] ?? 0;
        $total = array_sum($healthCounts);

        // Get links that need checking (already filtered for active in scope)
        $needsChecking = Link::needsHealthCheck()->count();

        // Get last check time
        $lastChecked = Link::whereNotNull('last_checked_at')
            ->orderBy('last_checked_at', 'desc')
            ->first();

        $lastCheckedText = $lastChecked
            ? 'Last check: '.$lastChecked->last_checked_at->diffForHumans()
            : 'No checks performed yet';

        // Calculate health percentage
        $checkedTotal = $healthy + $warning + $error + $blocked + $timeout;
        $healthPercentage = $checkedTotal > 0
            ? round(($healthy / $checkedTotal) * 100, 1)
            : 0;

        return [
            Stat::make('Healthy Links', $healthy)
                ->description($healthPercentage.'% of checked links')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->chart($this->getHealthTrend('healthy')),

            Stat::make('Warning Links', $warning + $timeout)
                ->description($timeout > 0 ? "Warnings: {$warning}, Timeouts: {$timeout}" : 'May have redirects or issues')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('warning')
                ->chart($this->getHealthTrend('warning')),

            Stat::make('Problem Links', $error + $blocked)
                ->description("Errors: {$error}, Blocked: {$blocked}")
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->color('danger')
                ->chart($this->getHealthTrend('error')),

            Stat::make('Link Health Status', "{$checkedTotal}/{$total}")
                ->description($lastCheckedText)
                ->descriptionIcon('heroicon-m-shield-check')
                ->color($needsChecking > 10 ? 'warning' : 'primary')
                ->extraAttributes([
                    'class' => 'cursor-pointer',
                    'title' => $needsChecking.' links need checking',
                ]),
        ];
    }

    /**
     * Get trend data for health status over last 7 days
     */
    private function getHealthTrend(string $status): array
    {
        // For now, return empty array - could be enhanced to track historical data
        return [];
    }

    /**
     * Poll every 5 minutes to update health status
     */
    protected ?string $pollingInterval = '300s';
}
