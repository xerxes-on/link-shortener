<?php

namespace App\Filament\Widgets;

use App\Models\Link;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

class TrendingLinksWidget extends BaseWidget
{
    protected static ?string $heading = 'Trending Links';

    protected ?string $description = 'Links gaining momentum in the last 24 hours';

    protected static ?int $sort = 4; // This will place it above TopLinksWidget (which has sort = 5)

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        // Get trending links with click growth calculation
        $subQuery = DB::table('clicks')
            ->select('link_id')
            ->selectRaw('COUNT(*) as recent_clicks')
            ->selectRaw('COUNT(CASE WHEN clicked_at >= ? THEN 1 END) as last_hour_clicks', [now()->subHour()])
            ->where('clicked_at', '>=', now()->subDay())
            ->groupBy('link_id')
            ->having('recent_clicks', '>=', 5) // Minimum threshold to be considered trending
            ->orderByDesc('last_hour_clicks')
            ->orderByDesc('recent_clicks')
            ->limit(10);

        return $table
            ->query(
                Link::query()
                    ->joinSub($subQuery, 'trending', function ($join) {
                        $join->on('links.id', '=', 'trending.link_id');
                    })
                    ->with('group')
                    ->select('links.*', 'trending.recent_clicks', 'trending.last_hour_clicks')
                    ->orderByDesc('trending.last_hour_clicks')
                    ->orderByDesc('trending.recent_clicks')
            )
            ->columns([
                TextColumn::make('short_code')
                    ->label('Short Code')
                    ->url(fn (Link $record): string => url($record->short_code))
                    ->openUrlInNewTab()
                    ->copyable()
                    ->copyableState(fn (Link $record): string => url($record->short_code))
                    ->copyMessage('Short URL copied!')
                    ->copyMessageDuration(1500)
                    ->badge()
                    ->color(fn (Link $record) => $record->group ? Color::hex($record->group->color) : 'primary'),

                TextColumn::make('original_url')
                    ->label('Destination')
                    ->limit(40)
                    ->tooltip(fn (Link $record): string => $record->original_url)
                    ->url(fn (Link $record): string => route('filament.admin.resources.links.edit', $record))
                    ->color('gray'),

                TextColumn::make('recent_clicks')
                    ->label('24h Clicks')
                    ->badge()
                    ->color('success')
                    ->alignCenter(),

                TextColumn::make('last_hour_clicks')
                    ->label('Last Hour')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray')
                    ->alignCenter(),

                TextColumn::make('trend')
                    ->label('Momentum')
                    ->getStateUsing(function ($record) {
                        if ($record->last_hour_clicks == 0) {
                            return '→';
                        }

                        // Calculate hourly average for the past 24 hours
                        $hourlyAverage = $record->recent_clicks / 24;

                        // If last hour is significantly above average, it's trending up
                        if ($record->last_hour_clicks > $hourlyAverage * 1.5) {
                            return '↑↑';
                        } elseif ($record->last_hour_clicks > $hourlyAverage * 1.1) {
                            return '↑';
                        } else {
                            return '→';
                        }
                    })
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        '↑↑' => 'success',
                        '↑' => 'info',
                        default => 'gray'
                    })
                    ->alignCenter(),

                TextColumn::make('created_at')
                    ->label('Age')
                    ->since(),
            ])
            ->paginated(false)
            ->poll('30s'); // Auto-refresh every 30 seconds to show real-time trends
    }
}
