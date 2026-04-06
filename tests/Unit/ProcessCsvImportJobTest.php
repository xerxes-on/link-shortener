<?php

namespace Tests\Unit;

use App\Jobs\ProcessCsvImportJob;
use App\Models\Link;
use App\Models\User;
use App\Services\CsvImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProcessCsvImportJobTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('create_link');

        $this->user = User::factory()->create();
        $this->user->givePermissionTo('create_link');

        Storage::fake('local');
    }

    public function test_job_processes_valid_csv(): void
    {
        $csvContent = implode(',', CsvImportService::EXPECTED_COLUMNS)."\n";
        $csvContent .= 'https://example.com,test-slug,Test Group,,,,,';

        $filePath = 'csv-imports/test.csv';
        Storage::disk('local')->put($filePath, $csvContent);

        $job = new ProcessCsvImportJob($filePath, $this->user->id);
        $job->handle();

        $this->assertDatabaseHas('links', [
            'short_code' => 'test-slug',
            'original_url' => 'https://example.com',
            'created_by' => $this->user->id,
        ]);

        // File should be cleaned up
        $this->assertFalse(Storage::disk('local')->exists($filePath));
    }

    public function test_job_handles_invalid_csv(): void
    {
        $csvContent = "invalid,csv,format\nnot-a-url,test,";

        $filePath = 'csv-imports/invalid.csv';
        Storage::disk('local')->put($filePath, $csvContent);

        $job = new ProcessCsvImportJob($filePath, $this->user->id);
        $job->handle();

        $this->assertEquals(0, Link::count());

        // File should be cleaned up even on failure
        $this->assertFalse(Storage::disk('local')->exists($filePath));
    }

    public function test_job_handles_missing_file(): void
    {
        $job = new ProcessCsvImportJob('nonexistent.csv', $this->user->id);

        // Should not throw exception
        $job->handle();

        $this->assertEquals(0, Link::count());
    }

    public function test_job_handles_invalid_user(): void
    {
        $csvContent = implode(',', CsvImportService::EXPECTED_COLUMNS)."\n";
        $csvContent .= 'https://example.com,test-slug,,,,,,,';

        $filePath = 'csv-imports/test.csv';
        Storage::disk('local')->put($filePath, $csvContent);

        $job = new ProcessCsvImportJob($filePath, 99999); // Non-existent user

        // Should not throw exception
        $job->handle();

        $this->assertEquals(0, Link::count());

        // File should be cleaned up
        $this->assertFalse(Storage::disk('local')->exists($filePath));
    }

    public function test_job_stores_results_with_session_id(): void
    {
        $csvContent = implode(',', CsvImportService::EXPECTED_COLUMNS)."\n";
        $csvContent .= 'https://example.com,test-slug,,,,,,,';

        $filePath = 'csv-imports/test.csv';
        $sessionId = 'test-session-123';

        Storage::disk('local')->put($filePath, $csvContent);

        $job = new ProcessCsvImportJob($filePath, $this->user->id, $sessionId);
        $job->handle();

        $this->assertTrue(
            Storage::disk('local')->exists("csv-import-results/{$sessionId}.json")
        );

        $results = json_decode(
            Storage::disk('local')->get("csv-import-results/{$sessionId}.json"),
            true
        );

        $this->assertTrue($results['success']);
        $this->assertEquals(1, $results['imported']);
        $this->assertEquals(1, $results['total']);
    }

    public function test_job_can_be_dispatched(): void
    {
        Queue::fake();

        ProcessCsvImportJob::dispatch('test.csv', $this->user->id);

        Queue::assertPushed(ProcessCsvImportJob::class);
    }

    public function test_job_cleanup_on_failure(): void
    {
        $filePath = 'csv-imports/test.csv';
        Storage::disk('local')->put($filePath, 'some content');

        $job = new ProcessCsvImportJob($filePath, $this->user->id, 'session-123');

        // Simulate job failure
        $exception = new \Exception('Test failure');
        $job->failed($exception);

        // File should be cleaned up
        $this->assertFalse(Storage::disk('local')->exists($filePath));

        // Should store failure result
        $this->assertTrue(
            Storage::disk('local')->exists('csv-import-results/session-123.json')
        );

        $results = json_decode(
            Storage::disk('local')->get('csv-import-results/session-123.json'),
            true
        );

        $this->assertFalse($results['success']);
        $this->assertStringContainsString('Test failure', $results['error']);
    }

    public function test_job_checks_user_permissions(): void
    {
        // Create user without link creation permission
        $user = User::factory()->create();

        $csvContent = implode(',', CsvImportService::EXPECTED_COLUMNS)."\n";
        $csvContent .= 'https://example.com,test-slug,,,,,,,';

        $filePath = 'csv-imports/test.csv';
        Storage::disk('local')->put($filePath, $csvContent);

        $job = new ProcessCsvImportJob($filePath, $user->id);
        $job->handle();

        // Should not import anything due to lack of permissions
        $this->assertEquals(0, Link::count());

        // File should be cleaned up
        $this->assertFalse(Storage::disk('local')->exists($filePath));
    }
}
