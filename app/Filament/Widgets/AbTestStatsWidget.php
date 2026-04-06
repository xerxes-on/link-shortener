<?php

namespace App\Filament\Widgets;

use App\Models\AbTest;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class AbTestStatsWidget extends BaseWidget
{
    protected static ?string $heading = 'Active A/B Tests';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 10;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AbTest::query()
                    ->with(['variants', 'link'])
                    ->whereHas('link', function (Builder $query) {
                        $query->where('is_active', true);
                    })
                    ->where('is_active', true)
                    ->where(function (Builder $query) {
                        $query->whereNull('starts_at')
                            ->orWhere('starts_at', '<=', now());
                    })
                    ->where(function (Builder $query) {
                        $query->whereNull('ends_at')
                            ->orWhere('ends_at', '>', now());
                    })
                    ->orderBy('created_at', 'desc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Test Name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (AbTest $record): ?string => $record->description),

                Tables\Columns\TextColumn::make('link.short_code')
                    ->label('Link')
                    ->formatStateUsing(fn ($state) => url($state))
                    ->copyable()
                    ->copyableState(fn ($record) => url($record->link->short_code))
                    ->copyMessage('Link copied!')
                    ->tooltip('Click to copy'),

                Tables\Columns\TextColumn::make('variants_count')
                    ->counts('variants')
                    ->label('Variants')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('total_clicks')
                    ->getStateUsing(function (AbTest $record): int {
                        return $record->variants->sum('click_count');
                    })
                    ->label('Total Clicks')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('leading_variant')
                    ->getStateUsing(function (AbTest $record): string {
                        $leadingVariant = $record->variants
                            ->sortByDesc('click_count')
                            ->first();

                        if (! $leadingVariant || $leadingVariant->click_count === 0) {
                            return 'No data yet';
                        }

                        $totalClicks = $record->variants->sum('click_count');
                        $percentage = $totalClicks > 0
                            ? round(($leadingVariant->click_count / $totalClicks) * 100, 1)
                            : 0;

                        return "{$leadingVariant->name} ({$percentage}%)";
                    })
                    ->label('Leading Variant')
                    ->icon('heroicon-o-trophy')
                    ->iconColor('warning'),

                Tables\Columns\TextColumn::make('click_distribution')
                    ->getStateUsing(function (AbTest $record): string {
                        $totalClicks = $record->variants->sum('click_count');

                        if ($totalClicks === 0) {
                            return 'No clicks yet';
                        }

                        $distributions = $record->variants
                            ->map(function ($variant) use ($totalClicks) {
                                $percentage = round(($variant->click_count / $totalClicks) * 100, 1);

                                return "{$variant->name}: {$percentage}%";
                            })
                            ->join(' | ');

                        return $distributions;
                    })
                    ->label('Click Distribution')
                    ->alignCenter()
                    ->wrap(),

                Tables\Columns\TextColumn::make('status')
                    ->getStateUsing(function (AbTest $record): string {
                        $totalClicks = $record->variants->sum('click_count');

                        if ($totalClicks === 0) {
                            return 'No Traffic';
                        } elseif ($totalClicks < 100) {
                            return 'Collecting Data';
                        } else {
                            return 'Statistically Significant';
                        }
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'No Traffic' => 'gray',
                        'Collecting Data' => 'warning',
                        'Statistically Significant' => 'success',
                        default => 'gray',
                    })
                    ->label('Status'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Started')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->since()
                    ->tooltip(fn ($record) => $record->created_at->format('M j, Y g:i A')),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Actions\Action::make('view_details')
                    ->label('View Details')
                    ->icon('heroicon-o-chart-bar')
                    ->color('info')
                    ->url(fn (AbTest $record): string => route('filament.admin.resources.links.edit', [
                        'record' => $record->link_id,
                    ]).'#ab-test'
                    )
                    ->tooltip('View A/B test details'),
            ])
            ->emptyStateHeading('No Active A/B Tests')
            ->emptyStateDescription('Create A/B tests to compare different destination URLs and optimize your link performance.')
            ->emptyStateIcon('heroicon-o-beaker')
            ->poll('30s'); // Refresh every 30 seconds
    }

    protected function getTableRecordsPerPageSelectOptions(): array
    {
        return [5, 10, 25];
    }

    public static function canView(): bool
    {
        // Only show if there are any A/B tests in the system
        return AbTest::exists();
    }
}
