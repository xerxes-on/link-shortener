<?php

namespace App\Jobs;

use App\Services\GoogleAnalyticsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendGoogleAnalyticsEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public $timeout = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $clickData
    ) {
        $this->onQueue('analytics');
    }

    /**
     * Execute the job.
     */
    public function handle(GoogleAnalyticsService $gaService): void
    {
        if (! $gaService->isEnabled()) {
            Log::debug('GA4 integration not enabled, skipping event');

            return;
        }

        try {
            $success = $gaService->sendClickEvent($this->clickData);

            if (! $success) {
                Log::warning('Failed to send GA4 event', [
                    'click_data' => $this->clickData,
                    'attempt' => $this->attempts(),
                ]);

                // For GA failures, we don't want to retry since it's not critical
                // Just log and continue - GA tracking is supplementary to core functionality
            }
        } catch (\Throwable $e) {
            Log::error('GA4 event exception in job', [
                'error' => $e->getMessage(),
                'click_data' => $this->clickData,
            ]);

            // GA tracking is supplementary - don't let exceptions affect core functionality
        }
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('GA4 event job failed permanently', [
            'click_data' => $this->clickData,
            'error' => $exception->getMessage(),
        ]);
    }
}
