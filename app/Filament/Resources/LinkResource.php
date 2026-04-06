<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LinkResource\Pages;
use App\Filament\Resources\LinkResource\RelationManagers;
use App\Jobs\CheckLinkHealthJob;
use App\Models\Link;
use App\Models\LinkGroup;
use App\Services\TimezoneService;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Response;

class LinkResource extends Resource
{
    protected static ?string $model = Link::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-link';

    protected static \UnitEnum|string|null $navigationGroup = 'Link Management';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->schema([
                Schemas\Components\Section::make('Link Details')
                    ->schema([
                        Forms\Components\TextInput::make('custom_slug')
                            ->label('Short URL')
                            ->placeholder('my-custom-url')
                            ->helperText('Only letters, numbers, hyphens, and underscores allowed.')
                            ->regex('/^[a-z0-9\-_]*$/')
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('original_url')
                            ->label('Destination URL')
                            ->required()
                            ->url()
                            ->placeholder('https://example.com/long-url'),
                        Forms\Components\Select::make('group_id')
                            ->label('Group')
                            ->relationship('group', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText(function () {
                                $defaultGroup = LinkGroup::where('is_default', true)->first();

                                return $defaultGroup
                                    ? "Leave empty to use default: {$defaultGroup->name}"
                                    : null;
                            })
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required(),
                                Forms\Components\TextInput::make('description'),
                                Forms\Components\ColorPicker::make('color')
                                    ->default('#3B82F6'),
                            ]),
                        Forms\Components\Select::make('redirect_type')
                            ->label('Redirect Type')
                            ->options([
                                301 => '301 - Permanent',
                                302 => '302 - Temporary',
                                307 => '307 - Temporary (preserve method)',
                                308 => '308 - Permanent (preserve method)',
                            ])
                            ->default(302)
                            ->required(),
                    ])->columns(2),
                Schemas\Components\Section::make('Security & Limits')
                    ->schema([
                        Forms\Components\TextInput::make('password')
                            ->label('Password Protection')
                            ->password()
                            ->nullable()
                            ->helperText('Users will need to enter this password before accessing the link'),
                        Forms\Components\TextInput::make('click_limit')
                            ->label('Click Limit')
                            ->numeric()
                            ->nullable()
                            ->minValue(1)
                            ->helperText('Link will become unavailable after this many clicks'),
                    ])->columns(2),
                Schemas\Components\Section::make('Settings')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive links will return 404'),
                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Link Expiry')
                            ->native(false)
                            ->nullable()
                            ->minDate(now())
                            ->placeholder('Select when link should expire (optional)')
                            ->displayFormat('M j, Y g:i A')
                            ->extraInputAttributes([
                                'autocomplete' => 'off',
                                'data-form-type' => 'other',
                                'data-lpignore' => 'true',
                                'data-1p-ignore' => 'true',
                            ]),
                    ])->columns(2),
                Schemas\Components\Section::make('Health Monitoring')
                    ->schema([
                        Forms\Components\Toggle::make('exclude_from_health_checks')
                            ->label('Exclude from health checks')
                            ->default(false)
                            ->helperText('When enabled, this link will not be checked for health status or trigger notifications'),
                        Forms\Components\Toggle::make('notification_paused')
                            ->label('Pause notifications')
                            ->default(false)
                            ->helperText('Temporarily stop sending failure notifications for this link')
                            ->visible(fn ($record) => $record !== null && $record->notification_count > 0),
                        Forms\Components\Placeholder::make('health_status_info')
                            ->label('Current Status')
                            ->content(fn ($record) => $record ? ucfirst($record->health_status ?? 'Not checked') : 'Not checked yet')
                            ->visible(fn ($record) => $record !== null),
                        Forms\Components\Placeholder::make('notification_info')
                            ->label('Notifications Sent')
                            ->content(fn ($record) => $record && $record->notification_count > 0
                                ? "{$record->notification_count} notification(s) sent. Last sent: ".
                                  ($record->last_notification_sent_at ? TimezoneService::formatForUser($record->last_notification_sent_at, 'M j, Y g:i A') : 'Never')
                                : 'No notifications sent')
                            ->visible(fn ($record) => $record !== null && $record->notification_count > 0),
                    ])->columns(2),
                Forms\Components\Hidden::make('created_by')
                    ->default(auth()->id()),
                Forms\Components\Hidden::make('short_code'),

                // QR Code section - only show when editing existing links
                Schemas\Components\Section::make('QR Code')
                    ->schema([
                        ViewField::make('qr_code')
                            ->view('filament.forms.qr-code')
                            ->hiddenLabel(),
                    ])
                    ->visible(fn ($record) => $record !== null)
                    ->collapsible(),

                // Creation info section - only show when editing
                Schemas\Components\Section::make('Creation Information')
                    ->schema([
                        Forms\Components\Placeholder::make('created_by_info')
                            ->label('Created by')
                            ->content(fn ($record) => $record?->creator?->name ?? 'Unknown'),
                        Forms\Components\Placeholder::make('created_at_info')
                            ->label('Created on')
                            ->content(fn ($record) => $record ? TimezoneService::formatForUser($record->created_at, 'M j, Y g:i A') : null),
                        Forms\Components\Placeholder::make('updated_at_info')
                            ->label('Last updated')
                            ->content(fn ($record) => $record ? TimezoneService::formatForUser($record->updated_at, 'M j, Y g:i A') : null),
                    ])
                    ->columns(3)
                    ->visible(fn ($record) => $record !== null)
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('short_code')
                    ->label('Short URL')
                    ->searchable()
                    ->copyable()
                    ->copyableState(fn ($record) => url($record->short_code))
                    ->copyMessage('Short URL copied!')
                    ->copyMessageDuration(1500)
                    ->formatStateUsing(fn ($state) => url($state))
                    ->description(fn ($record) => $record->custom_slug ? 'Custom' : 'Generated')
                    ->tooltip('Click to copy'),
                Tables\Columns\TextColumn::make('original_url')
                    ->label('Destination')
                    ->limit(40)
                    ->tooltip(fn ($state) => $state)
                    ->searchable(),
                Tables\Columns\TextColumn::make('group.name')
                    ->label('Group')
                    ->html()
                    ->formatStateUsing(function ($state, $record) {
                        if (! $state) {
                            return '<span class="fi-badge flex items-center justify-center gap-x-1 rounded-md text-xs font-medium ring-1 ring-inset px-2 min-w-[theme(spacing.6)] py-1 fi-color-custom bg-custom-50 text-custom-600 ring-custom-600/10 dark:bg-custom-400/10 dark:text-custom-400 dark:ring-custom-400/30" style="--c-50: var(--gray-50); --c-400: var(--gray-400); --c-600: var(--gray-600);">Uncategorized</span>';
                        }

                        $color = $record->group->color ?? '#6B7280';
                        $textColor = self::getContrastColor($color);

                        return sprintf(
                            '<span class="fi-badge flex items-center justify-center gap-x-1 rounded-md text-xs font-medium ring-1 ring-inset px-2 min-w-[theme(spacing.6)] py-1" style="background-color: %s; color: %s; border-color: %s;">%s</span>',
                            $color,
                            $textColor,
                            $color,
                            e($state)
                        );
                    })
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('click_count')
                    ->label('Clicks')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->description(function ($record) {
                        if ($record->hasClickLimit()) {
                            $remaining = $record->remaining_clicks;

                            return $remaining > 0
                                ? "Limit: {$record->click_limit} ({$remaining} remaining)"
                                : "Limit reached ({$record->click_limit})";
                        }

                        return null;
                    }),
                Tables\Columns\IconColumn::make('password_protected')
                    ->label('🔒')
                    ->getStateUsing(fn ($record) => $record->hasPassword())
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->tooltip(fn ($record) => $record->hasPassword() ? 'Password protected' : 'No password'),
                Tables\Columns\IconColumn::make('health_status')
                    ->label('Health')
                    ->icon(fn ($record) => $record->health_status_icon)
                    ->color(fn ($record) => $record->health_status_color)
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->tooltip(function ($record) {
                        if (! $record->last_checked_at) {
                            return 'Not checked yet';
                        }

                        return sprintf(
                            '%s - Last checked: %s',
                            $record->health_check_message ?? 'Unknown',
                            TimezoneService::diffForUser($record->last_checked_at)
                        );
                    }),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active'),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->formatStateUsing(fn ($state) => $state ? TimezoneService::formatForUser($state, 'M j, Y') : 'Never')
                    ->placeholder('Never')
                    ->color(fn ($record) => $record->isExpired() ? 'danger' : null)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Created By')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->formatStateUsing(fn ($state) => TimezoneService::formatForUser($state, 'M j, Y g:i A'))
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('group')
                    ->relationship('group', 'name')
                    ->searchable()
                    ->preload()
                    ->indicator('Group'),
                Tables\Filters\SelectFilter::make('creator')
                    ->relationship('creator', 'name')
                    ->label('Created By')
                    ->searchable()
                    ->preload()
                    ->indicator('Created By')
                    ->visible(fn () => auth()->user()->can('view_any_link')),
                Tables\Filters\SelectFilter::make('health_status')
                    ->options([
                        'unchecked' => 'Not Checked',
                        'healthy' => 'Healthy',
                        'warning' => 'Warning',
                        'error' => 'Error',
                        'blocked' => 'Blocked',
                        'timeout' => 'Timeout',
                    ])
                    ->label('Health Status')
                    ->indicator('Health Status'),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
                Tables\Filters\Filter::make('expired')
                    ->query(fn (Builder $query) => $query->where('expires_at', '<', now()))
                    ->label('Expired'),
                Tables\Filters\Filter::make('password_protected')
                    ->query(fn (Builder $query) => $query->whereNotNull('password'))
                    ->label('Password Protected'),
                Tables\Filters\Filter::make('has_click_limit')
                    ->query(fn (Builder $query) => $query->whereNotNull('click_limit'))
                    ->label('Has Click Limit'),
                Tables\Filters\Filter::make('click_limit_exceeded')
                    ->query(fn (Builder $query) => $query->whereRaw('click_count >= click_limit AND click_limit IS NOT NULL'))
                    ->label('Click Limit Exceeded'),
            ])
            ->headerActions([
                Actions\Action::make('export')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->action(function ($livewire) {
                        // Get the filtered data using the same query as the table
                        $query = $livewire->getFilteredTableQuery();
                        $links = $query->with(['group', 'creator'])->get();

                        if ($links->isEmpty()) {
                            Notification::make()
                                ->title('No data to export')
                                ->warning()
                                ->send();

                            return;
                        }

                        $filename = 'links-export-'.now()->format('Y-m-d-H-i-s').'.csv';

                        $headers = [
                            'Content-Type' => 'text/csv',
                            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                        ];

                        $callback = function () use ($links) {
                            $file = fopen('php://output', 'w');

                            // CSV Headers
                            fputcsv($file, [
                                'Short Code',
                                'Full Short URL',
                                'Destination URL',
                                'Category',
                                'Click Count',
                                'Health Status',
                                'Is Active',
                                'Expires At',
                                'Created By',
                                'Created At',
                                'Last Updated',
                            ]);

                            // Export data
                            foreach ($links as $link) {
                                fputcsv($file, [
                                    $link->short_code,
                                    url($link->short_code),
                                    $link->original_url,
                                    $link->group?->name ?? 'Uncategorized',
                                    $link->click_count,
                                    ucfirst($link->health_status ?? 'unchecked'),
                                    $link->is_active ? 'Yes' : 'No',
                                    $link->expires_at?->format('Y-m-d H:i:s') ?? '',
                                    $link->creator?->name ?? '',
                                    $link->created_at->format('Y-m-d H:i:s'),
                                    $link->updated_at->format('Y-m-d H:i:s'),
                                ]);
                            }

                            fclose($file);
                        };

                        return Response::stream($callback, 200, $headers);
                    })
                    ->tooltip('Export filtered links as CSV'),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\Action::make('qr_code')
                    ->label('QR Code')
                    ->icon('heroicon-o-qr-code')
                    ->modalHeading('QR Code')
                    ->modalContent(fn ($record) => view('filament.modals.qr-code', ['link' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelAction(false)
                    ->tooltip('View QR code'),
                Actions\Action::make('view_stats')
                    ->label('Stats')
                    ->icon('heroicon-o-chart-bar')
                    ->url(fn ($record) => static::getUrl('edit', ['record' => $record->id]).'#clicks')
                    ->tooltip('View click statistics'),
                Actions\Action::make('check_health')
                    ->label('Check Health')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function ($record) {
                        CheckLinkHealthJob::dispatch($record);
                    })
                    ->tooltip('Check link health now')
                    ->requiresConfirmation()
                    ->modalHeading('Check Link Health')
                    ->modalDescription('This will check if the destination URL is still accessible.')
                    ->modalSubmitActionLabel('Check Now')
                    ->successNotificationTitle('Health check queued'),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-check-circle')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update(['is_active' => true]);
                            }
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Activate Links')
                        ->modalDescription('This will activate the selected links, making them accessible.')
                        ->modalSubmitActionLabel('Activate')
                        ->deselectRecordsAfterCompletion()
                        ->color('success'),
                    Actions\BulkAction::make('deactivate')
                        ->label('Deactivate')
                        ->icon('heroicon-o-x-circle')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update(['is_active' => false]);
                            }
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Deactivate Links')
                        ->modalDescription('This will deactivate the selected links. They will return 404 when accessed.')
                        ->modalSubmitActionLabel('Deactivate')
                        ->deselectRecordsAfterCompletion()
                        ->color('danger'),
                    Actions\BulkAction::make('check_health')
                        ->label('Check Health')
                        ->icon('heroicon-o-arrow-path')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                CheckLinkHealthJob::dispatch($record);
                            }
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Check Link Health')
                        ->modalDescription('This will check if the selected destination URLs are still accessible.')
                        ->modalSubmitActionLabel('Check Now')
                        ->deselectRecordsAfterCompletion(),
                    Actions\BulkAction::make('remove_expiry')
                        ->label('Remove Expiry Date')
                        ->icon('heroicon-o-calendar-days')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update(['expires_at' => null]);
                            }
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Remove Expiry Dates')
                        ->modalDescription('This will remove the expiration date from the selected links, making them permanent.')
                        ->modalSubmitActionLabel('Remove Expiry Dates')
                        ->deselectRecordsAfterCompletion()
                        ->color('warning'),
                    Actions\DeleteBulkAction::make(),
                    Actions\BulkAction::make('reset_click_counts')
                        ->label('Reset Click Counts')
                        ->icon('heroicon-o-arrow-path')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update(['click_count' => 0]);
                            }
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Reset Click Counts')
                        ->modalDescription('This will reset the click count to 0 for selected links. This action cannot be undone.')
                        ->modalSubmitActionLabel('Reset Counts')
                        ->deselectRecordsAfterCompletion()
                        ->color('warning'),
                    Actions\BulkAction::make('delete_all_clicks')
                        ->label('Delete All Click Data')
                        ->icon('heroicon-o-trash')
                        ->action(function ($records) {
                            $totalDeleted = 0;
                            foreach ($records as $record) {
                                $deleted = $record->clicks()->delete();
                                $totalDeleted += $deleted;
                                $record->update(['click_count' => 0]);
                            }

                            Notification::make()
                                ->title("Deleted {$totalDeleted} click records")
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Delete All Click Data')
                        ->modalDescription('This will permanently delete ALL click tracking data for the selected links and reset their click counts to 0. This action cannot be undone.')
                        ->modalSubmitActionLabel('Delete All Clicks')
                        ->deselectRecordsAfterCompletion()
                        ->color('danger'),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ClicksRelationManager::class,
            RelationManagers\GeoRulesRelationManager::class,
            RelationManagers\AbTestRelationManager::class,
            RelationManagers\NotificationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLinks::route('/'),
            'create' => Pages\CreateLink::route('/create'),
            'edit' => Pages\EditLink::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        // If user can only view their own links, filter by created_by
        if (! auth()->user()->can('view_any_link')) {
            $query->where('created_by', auth()->id());
        }

        return $query;
    }

    /**
     * Calculate appropriate text color (black or white) based on background color
     */
    private static function getContrastColor(string $hexColor): string
    {
        // Remove # if present
        $hex = ltrim($hexColor, '#');

        // Convert to RGB
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        // Calculate luminance
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        // Return black for light backgrounds, white for dark
        return $luminance > 0.5 ? '#000000' : '#ffffff';
    }
}
