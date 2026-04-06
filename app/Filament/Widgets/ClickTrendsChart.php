<?php

namespace App\Filament\Widgets;

use App\Models\Click;
use App\Services\TimezoneService;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class ClickTrendsChart extends ChartWidget
{
    protected ?string $heading = 'Click Trends';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public ?string $filter = '30';

    protected function getData(): array
    {
        $days = (int) $this->filter;
        $userTimezone = TimezoneService::getUserTimezone();

        // Get the date range in user's timezone, but convert to UTC for database queries
        $startDate = Carbon::now($userTimezone)->subDays($days)->startOfDay()->utc();
        $endDate = Carbon::now($userTimezone)->endOfDay()->utc();

        // Get all clicks in the period and group them by date in user's timezone
        $clicks = Click::whereBetween('clicked_at', [$startDate, $endDate])->get();

        $clickData = $clicks->groupBy(function ($click) use ($userTimezone) {
            return Carbon::parse($click->clicked_at)->setTimezone($userTimezone)->format('Y-m-d');
        })->map(function ($group, $date) {
            return (object) ['date' => $date, 'clicks' => $group->count()];
        });

        // Create array of all dates in range to fill gaps (in user timezone)
        $dateRange = collect();
        for ($i = $days - 1; $i >= 0; $i--) {
            $dateRange->push(Carbon::now($userTimezone)->subDays($i)->format('Y-m-d'));
        }

        // Map click data to dates, filling missing dates with 0
        $chartData = $dateRange->map(function ($date) use ($clickData) {
            $dayData = $clickData->firstWhere('date', $date);

            return $dayData ? $dayData->clicks : 0;
        });

        $labels = $dateRange->map(function ($date) {
            return Carbon::parse($date)->format('M j');
        });

        return [
            'datasets' => [
                [
                    'label' => 'Daily Clicks',
                    'data' => $chartData->values()->toArray(),
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
            'labels' => $labels->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getFilters(): ?array
    {
        return [
            '7' => 'Last 7 days',
            '30' => 'Last 30 days',
            '90' => 'Last 3 months',
        ];
    }

    public function getHeading(): string
    {
        $filterLabels = [
            '7' => 'Last 7 Days',
            '30' => 'Last 30 Days',
            '90' => 'Last 3 Months',
        ];

        $selectedLabel = $filterLabels[$this->filter] ?? 'Last 30 Days';

        return 'Click Trends ('.$selectedLabel.')';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                ],
            ],
        ];
    }
}
