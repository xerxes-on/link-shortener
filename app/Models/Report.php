<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'user_id',
        'layout_config',
        'global_filters',
        'schedule_config',
        'is_template',
        'visibility',
        'is_active',
        'last_generated_at',
    ];

    protected $casts = [
        'layout_config' => 'array',
        'global_filters' => 'array',
        'schedule_config' => 'array',
        'is_template' => 'boolean',
        'is_active' => 'boolean',
        'last_generated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(ReportComponent::class)->orderBy('order_index');
    }

    public function containers(): HasMany
    {
        return $this->hasMany(ReportContainer::class)->orderBy('order_index');
    }

    public function isVisible(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        // Public reports are visible to everyone (even unauthenticated users)
        if ($this->visibility === 'public') {
            return auth()->check() ? auth()->user()->can('view_report') : true;
        }

        // For private/team reports, user must be authenticated
        if (! auth()->check()) {
            return false;
        }

        $user = auth()->user();

        // Check basic view permission
        if (! $user->can('view_report')) {
            return false;
        }

        // Own reports
        if ($this->user_id === $user->id) {
            return true;
        }

        // Team reports
        if ($this->visibility === 'team') {
            return $user->can('view_any_report');
        }

        return false;
    }

    private function canViewTeamReports(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin']);
    }

    public function getDateRangeAttribute(): array
    {
        $filters = $this->global_filters ?? [];

        // Handle relative date ranges
        if (($filters['date_range_type'] ?? 'relative') === 'relative') {
            $period = $filters['relative_period'] ?? 'last_30_days';

            return $this->calculateRelativeDateRange($period);
        }

        // Handle fixed date ranges
        return [
            'start_date' => $filters['start_date'] ?? now()->subDays(30)->toDateString(),
            'end_date' => $filters['end_date'] ?? now()->toDateString(),
        ];
    }

    private function calculateRelativeDateRange(string $period): array
    {
        $now = now();

        return match ($period) {
            'last_7_days' => [
                'start_date' => $now->copy()->subDays(7)->toDateString(),
                'end_date' => $now->toDateString(),
            ],
            'last_30_days' => [
                'start_date' => $now->copy()->subDays(30)->toDateString(),
                'end_date' => $now->toDateString(),
            ],
            'last_90_days' => [
                'start_date' => $now->copy()->subDays(90)->toDateString(),
                'end_date' => $now->toDateString(),
            ],
            'last_6_months' => [
                'start_date' => $now->copy()->subMonths(6)->toDateString(),
                'end_date' => $now->toDateString(),
            ],
            'last_year' => [
                'start_date' => $now->copy()->subYear()->toDateString(),
                'end_date' => $now->toDateString(),
            ],
            'this_week' => [
                'start_date' => $now->copy()->startOfWeek()->toDateString(),
                'end_date' => $now->copy()->endOfWeek()->toDateString(),
            ],
            'this_month' => [
                'start_date' => $now->copy()->startOfMonth()->toDateString(),
                'end_date' => $now->copy()->endOfMonth()->toDateString(),
            ],
            'this_quarter' => [
                'start_date' => $now->copy()->startOfQuarter()->toDateString(),
                'end_date' => $now->copy()->endOfQuarter()->toDateString(),
            ],
            'this_year' => [
                'start_date' => $now->copy()->startOfYear()->toDateString(),
                'end_date' => $now->copy()->endOfYear()->toDateString(),
            ],
            'yesterday' => [
                'start_date' => $now->copy()->subDay()->toDateString(),
                'end_date' => $now->copy()->subDay()->toDateString(),
            ],
            'last_week' => [
                'start_date' => $now->copy()->subWeek()->startOfWeek()->toDateString(),
                'end_date' => $now->copy()->subWeek()->endOfWeek()->toDateString(),
            ],
            'last_month' => [
                'start_date' => $now->copy()->subMonth()->startOfMonth()->toDateString(),
                'end_date' => $now->copy()->subMonth()->endOfMonth()->toDateString(),
            ],
            default => [
                'start_date' => $now->copy()->subDays(30)->toDateString(),
                'end_date' => $now->toDateString(),
            ],
        };
    }
}
