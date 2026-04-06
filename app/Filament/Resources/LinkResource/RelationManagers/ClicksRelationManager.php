<?php

namespace App\Filament\Resources\LinkResource\RelationManagers;

use App\Models\Click;
use App\Services\TimezoneService;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Response;

class ClicksRelationManager extends RelationManager
{
    protected static string $relationship = 'clicks';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('ip_address')
                    ->label('IP Address')
                    ->disabled(),
                Forms\Components\TextInput::make('country')
                    ->label('Country')
                    ->disabled(),
                Forms\Components\TextInput::make('city')
                    ->label('City')
                    ->disabled(),
                Forms\Components\Textarea::make('user_agent')
                    ->label('User Agent')
                    ->disabled(),
                Forms\Components\TextInput::make('referer')
                    ->label('Referrer')
                    ->disabled(),
                Forms\Components\DateTimePicker::make('clicked_at')
                    ->label('Clicked At')
                    ->disabled(),
                Forms\Components\TextInput::make('utm_source')
                    ->label('UTM Source')
                    ->disabled(),
                Forms\Components\TextInput::make('utm_medium')
                    ->label('UTM Medium')
                    ->disabled(),
                Forms\Components\TextInput::make('utm_campaign')
                    ->label('UTM Campaign')
                    ->disabled(),
                Forms\Components\TextInput::make('utm_term')
                    ->label('UTM Term')
                    ->disabled(),
                Forms\Components\TextInput::make('utm_content')
                    ->label('UTM Content')
                    ->disabled(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('clicked_at')
            ->columns([
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('country')
                    ->label('Country')
                    ->searchable()
                    ->badge()
                    ->color('primary')
                    ->placeholder('Unknown'),
                Tables\Columns\TextColumn::make('city')
                    ->label('City')
                    ->searchable()
                    ->placeholder('Unknown'),
                Tables\Columns\TextColumn::make('user_agent')
                    ->label('Browser/Device')
                    ->limit(30)
                    ->tooltip(fn ($state) => $state)
                    ->formatStateUsing(function ($state) {
                        if (str_contains($state, 'Mobile')) {
                            return 'Mobile';
                        } elseif (str_contains($state, 'Chrome')) {
                            return 'Chrome';
                        } elseif (str_contains($state, 'Firefox')) {
                            return 'Firefox';
                        } elseif (str_contains($state, 'Safari')) {
                            return 'Safari';
                        } elseif (str_contains($state, 'Edge')) {
                            return 'Edge';
                        }

                        return 'Other';
                    }),
                Tables\Columns\TextColumn::make('referer')
                    ->label('Referrer')
                    ->limit(25)
                    ->placeholder('Direct')
                    ->formatStateUsing(fn ($state) => $state ? parse_url($state, PHP_URL_HOST) : 'Direct'),
                Tables\Columns\TextColumn::make('utm_campaign')
                    ->label('Campaign')
                    ->badge()
                    ->color('info')
                    ->placeholder('None')
                    ->toggleable()
                    ->tooltip(function ($record) {
                        $utmData = [];
                        if ($record->utm_source) {
                            $utmData[] = "Source: {$record->utm_source}";
                        }
                        if ($record->utm_medium) {
                            $utmData[] = "Medium: {$record->utm_medium}";
                        }
                        if ($record->utm_campaign) {
                            $utmData[] = "Campaign: {$record->utm_campaign}";
                        }
                        if ($record->utm_term) {
                            $utmData[] = "Term: {$record->utm_term}";
                        }
                        if ($record->utm_content) {
                            $utmData[] = "Content: {$record->utm_content}";
                        }

                        return ! empty($utmData) ? implode("\n", $utmData) : 'No UTM data';
                    }),
                Tables\Columns\TextColumn::make('utm_source')
                    ->label('Source')
                    ->placeholder('None')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('utm_medium')
                    ->label('Medium')
                    ->placeholder('None')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('clicked_at')
                    ->label('Time')
                    ->formatStateUsing(fn ($state) => TimezoneService::formatForUser($state, 'M j, Y g:i A'))
                    ->sortable(),
            ])
            ->defaultSort('clicked_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('country')
                    ->options(function () {
                        return Click::whereNotNull('country')
                            ->distinct()
                            ->pluck('country', 'country')
                            ->toArray();
                    }),
                Tables\Filters\Filter::make('has_location')
                    ->query(fn (Builder $query) => $query->whereNotNull('country'))
                    ->label('Has Location Data'),
                Tables\Filters\Filter::make('today')
                    ->query(function (Builder $query) {
                        $userTimezone = TimezoneService::getUserTimezone();
                        $userToday = Carbon::now($userTimezone)->startOfDay();

                        return $query->whereBetween('clicked_at', [
                            $userToday->utc(),
                            $userToday->copy()->endOfDay()->utc(),
                        ]);
                    })
                    ->label('Today'),
                Tables\Filters\Filter::make('this_week')
                    ->query(function (Builder $query) {
                        $userTimezone = TimezoneService::getUserTimezone();
                        $startOfWeek = Carbon::now($userTimezone)->startOfWeek();
                        $endOfWeek = Carbon::now($userTimezone)->endOfWeek();

                        return $query->whereBetween('clicked_at', [
                            $startOfWeek->utc(),
                            $endOfWeek->utc(),
                        ]);
                    })
                    ->label('This Week'),
                Tables\Filters\SelectFilter::make('utm_source')
                    ->options(function () {
                        return Click::whereNotNull('utm_source')
                            ->distinct()
                            ->pluck('utm_source', 'utm_source')
                            ->toArray();
                    })
                    ->label('UTM Source'),
                Tables\Filters\SelectFilter::make('utm_medium')
                    ->options(function () {
                        return Click::whereNotNull('utm_medium')
                            ->distinct()
                            ->pluck('utm_medium', 'utm_medium')
                            ->toArray();
                    })
                    ->label('UTM Medium'),
                Tables\Filters\SelectFilter::make('utm_campaign')
                    ->options(function () {
                        return Click::whereNotNull('utm_campaign')
                            ->distinct()
                            ->pluck('utm_campaign', 'utm_campaign')
                            ->toArray();
                    })
                    ->label('UTM Campaign'),
                Tables\Filters\Filter::make('has_utm')
                    ->query(fn (Builder $query) => $query->where(function ($q) {
                        $q->whereNotNull('utm_source')
                            ->orWhereNotNull('utm_medium')
                            ->orWhereNotNull('utm_campaign')
                            ->orWhereNotNull('utm_term')
                            ->orWhereNotNull('utm_content');
                    }))
                    ->label('Has UTM Data'),
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('clicked_at', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('clicked_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = 'From '.Carbon::parse($data['from'])->toFormattedDateString();
                        }

                        if ($data['until'] ?? null) {
                            $indicators[] = 'Until '.Carbon::parse($data['until'])->toFormattedDateString();
                        }

                        return $indicators;
                    })
                    ->label('Date Range'),
            ])
            ->headerActions([
                Actions\Action::make('export')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->action(function () {
                        $link = $this->getOwnerRecord();

                        // Get the filtered data using the same query as the table
                        $query = $this->getFilteredTableQuery();
                        $clicks = $query->with('abTestVariant')->get();

                        if ($clicks->isEmpty()) {
                            Notification::make()
                                ->title('No data to export')
                                ->warning()
                                ->send();

                            return;
                        }

                        $filename = 'clicks-'.$link->short_code.'-'.now()->format('Y-m-d-H-i-s').'.csv';

                        $headers = [
                            'Content-Type' => 'text/csv',
                            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                        ];

                        $callback = function () use ($clicks) {
                            $file = fopen('php://output', 'w');

                            // CSV Headers
                            fputcsv($file, [
                                'Date & Time',
                                'IP Address',
                                'Country',
                                'City',
                                'Browser/Device',
                                'Referrer',
                                'UTM Source',
                                'UTM Medium',
                                'UTM Campaign',
                                'UTM Term',
                                'UTM Content',
                                'A/B Variant',
                            ]);

                            // Export data
                            foreach ($clicks as $click) {
                                fputcsv($file, [
                                    TimezoneService::formatForUser($click->clicked_at, 'Y-m-d H:i:s'),
                                    $click->ip_address,
                                    $click->country ?? '',
                                    $click->city ?? '',
                                    $click->user_agent,
                                    $click->referer ?? '',
                                    $click->utm_source ?? '',
                                    $click->utm_medium ?? '',
                                    $click->utm_campaign ?? '',
                                    $click->utm_term ?? '',
                                    $click->utm_content ?? '',
                                    $click->abTestVariant?->name ?? '',
                                ]);
                            }

                            fclose($file);
                        };

                        return Response::stream($callback, 200, $headers);
                    })
                    ->tooltip('Export filtered click data as CSV'),
                Actions\Action::make('delete_all_clicks')
                    ->label('Delete All Clicks')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->action(function () {
                        $link = $this->getOwnerRecord();
                        $deletedCount = $link->clicks()->count();
                        $link->clicks()->delete();
                        $link->update(['click_count' => 0]);

                        Notification::make()
                            ->title("Deleted {$deletedCount} click records for this link")
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Delete All Click Data')
                    ->modalDescription('This will permanently delete ALL click tracking data for this link and reset its click count to 0. This action cannot be undone.')
                    ->modalSubmitActionLabel('Delete All Clicks')
                    ->tooltip('Delete all click data for this link'),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\DeleteAction::make()
                    ->action(function ($record) {
                        // Delete the record
                        $record->delete();

                        // Update the link's click count by subtracting 1
                        $link = $this->getOwnerRecord();
                        $currentCount = $link->click_count;
                        $newCount = max(0, $currentCount - 1);
                        $link->update(['click_count' => $newCount]);

                        Notification::make()
                            ->title('Click record deleted and link count updated')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Delete Click Record')
                    ->modalDescription('This will permanently delete this click record and update the link\'s click count. This action cannot be undone.')
                    ->successNotificationTitle(null), // Disable default notification
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make()
                        ->action(function ($records) {
                            $deletedCount = $records->count();

                            // Delete the records
                            $records->each(fn ($record) => $record->delete());

                            // Update the link's click count by subtracting deleted clicks
                            $link = $this->getOwnerRecord();
                            $currentCount = $link->click_count;
                            $newCount = max(0, $currentCount - $deletedCount);
                            $link->update(['click_count' => $newCount]);

                            Notification::make()
                                ->title("Deleted {$deletedCount} click records and updated link count")
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Delete Selected Click Records')
                        ->modalDescription('This will permanently delete the selected click records and update the link\'s click count accordingly. This action cannot be undone.')
                        ->successNotificationTitle(null), // Disable default notification since we have custom one
                ]),
            ]);
    }
}
