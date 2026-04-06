<?php

namespace App\Filament\Widgets;

use App\Models\Click;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class UtmCampaignStatsWidget extends BaseWidget
{
    protected static ?string $heading = 'Campaign Performance';

    protected ?string $description = 'Top performing UTM campaigns and sources';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 9;

    public function getTableRecordKey($record): string
    {
        // Create a unique key from the combination of UTM parameters
        return md5($record->utm_campaign.'|'.$record->utm_source.'|'.$record->utm_medium);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Click::query()
                    ->whereNotNull('utm_campaign')
                    ->selectRaw('
                        utm_campaign, 
                        utm_source, 
                        utm_medium,
                        COUNT(*) as click_count,
                        COUNT(DISTINCT link_id) as link_count,
                        MAX(clicked_at) as last_click
                    ')
                    ->groupBy(['utm_campaign', 'utm_source', 'utm_medium'])
                    ->orderByDesc('click_count')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('utm_campaign')
                    ->label('Campaign')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('utm_source')
                    ->label('Source')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('utm_medium')
                    ->label('Medium')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('click_count')
                    ->label('Clicks')
                    ->sortable()
                    ->numeric()
                    ->alignEnd()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('link_count')
                    ->label('Links')
                    ->sortable()
                    ->numeric()
                    ->alignEnd()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('last_click')
                    ->label('Last Activity')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('utm_source')
                    ->options(function () {
                        return Click::whereNotNull('utm_source')
                            ->distinct()
                            ->pluck('utm_source', 'utm_source')
                            ->take(20)
                            ->toArray();
                    })
                    ->label('Source'),

                Tables\Filters\SelectFilter::make('utm_medium')
                    ->options(function () {
                        return Click::whereNotNull('utm_medium')
                            ->distinct()
                            ->pluck('utm_medium', 'utm_medium')
                            ->take(20)
                            ->toArray();
                    })
                    ->label('Medium'),

                Tables\Filters\Filter::make('today')
                    ->query(fn (Builder $query) => $query->whereDate('clicked_at', today()))
                    ->label('Today'),

                Tables\Filters\Filter::make('this_week')
                    ->query(fn (Builder $query) => $query->whereBetween('clicked_at', [now()->startOfWeek(), now()->endOfWeek()]))
                    ->label('This Week'),

                Tables\Filters\Filter::make('this_month')
                    ->query(fn (Builder $query) => $query->whereMonth('clicked_at', now()->month))
                    ->label('This Month'),
            ])
            ->defaultSort('click_count', 'desc')
            ->emptyStateHeading('No Campaign Data')
            ->emptyStateDescription('No UTM campaign data found. Campaigns will appear here when links are clicked with UTM parameters.')
            ->emptyStateIcon('heroicon-o-chart-bar')
            ->paginated(false);
    }

    protected function getTableRecordsPerPageSelectOptions(): array
    {
        return [10, 25, 50];
    }

    public static function canView(): bool
    {
        // Check if user has permission to view campaign analytics
        return auth()->check() && (
            auth()->user()->hasRole('super_admin') ||
            auth()->user()->can('view_campaign_analytics') ||
            auth()->user()->hasRole('admin')
        );
    }
}
