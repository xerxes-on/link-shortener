<?php

namespace Tests\Feature;

use App\Filament\Pages\CsvImport;
use App\Models\Link;
use App\Models\LinkGroup;
use App\Models\User;
use App\Services\CsvImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CsvImportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        // Create role and permissions
        $role = Role::findOrCreate('admin');
        Permission::create(['name' => 'create_link']);
        Permission::create(['name' => 'page_CsvImport']);

        // Create user with role and permissions
        $this->user = User::factory()->create();
        $this->user->assignRole('admin');
        $this->user->givePermissionTo(['create_link', 'page_CsvImport']);

        $this->actingAs($this->user);
    }

    public function test_csv_import_page_requires_authentication(): void
    {
        auth()->logout();

        $response = $this->get('/admin/csv-import');

        $response->assertRedirect('/admin/login');
    }

    public function test_csv_import_page_requires_permission(): void
    {
        $userWithoutPermission = User::factory()->create();
        $this->actingAs($userWithoutPermission);

        $response = $this->get('/admin/csv-import');

        $response->assertStatus(403);
    }

    public function test_csv_import_page_loads_for_authorized_user(): void
    {
        $response = $this->get('/admin/csv-import');

        $response->assertStatus(200);
        $response->assertSee('CSV Import');
    }

    public function test_can_download_csv_template(): void
    {
        // In Livewire 4/Filament 5, stream downloads are handled differently in tests.
        // Test that calling downloadTemplate does not throw an error.
        Livewire::test(CsvImport::class)
            ->call('downloadTemplate')
            ->assertSuccessful();
    }

    public function test_csv_template_contains_expected_structure(): void
    {
        // Test the template content via the service directly
        $content = CsvImportService::getSampleCsv();

        $this->assertStringContainsString('original_url', $content);
        $this->assertStringContainsString('custom_slug', $content);
        $this->assertStringContainsString('group_name', $content);
        $this->assertStringContainsString('https://github.com/laravel/laravel', $content);
    }

    public function test_can_import_valid_csv(): void
    {
        $csvContent = implode(',', CsvImportService::EXPECTED_COLUMNS)."\n";
        $csvContent .= 'https://example.com,test-slug,Test Group,2024-12-31,secret,100,302,1,Test note';

        // Store file to local disk and simulate import via stored path
        $filePath = 'csv-imports/test.csv';
        Storage::disk('local')->put($filePath, $csvContent);

        Livewire::test(CsvImport::class)
            ->set('data.csv_file', [$filePath])
            ->call('importCsv')
            ->assertRedirect('/admin/links');

        $this->assertDatabaseHas('links', [
            'short_code' => 'test-slug',
            'original_url' => 'https://example.com',
            'password' => 'secret',
            'click_limit' => 100,
            'redirect_type' => 302,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('link_groups', [
            'name' => 'Test Group',
        ]);
    }

    public function test_rejects_invalid_csv_format(): void
    {
        $csvContent = "wrong,header,format\nhttps://example.com,test,slug";

        $filePath = 'csv-imports/invalid.csv';
        Storage::disk('local')->put($filePath, $csvContent);

        Livewire::test(CsvImport::class)
            ->set('data.csv_file', [$filePath])
            ->call('importCsv')
            ->assertNotified('CSV Validation Failed');

        $this->assertEquals(0, Link::count());
    }

    public function test_rejects_invalid_url(): void
    {
        $csvContent = implode(',', CsvImportService::EXPECTED_COLUMNS)."\n";
        $csvContent .= 'not-a-url,test-slug,,,,,,,';

        $filePath = 'csv-imports/invalid-url.csv';
        Storage::disk('local')->put($filePath, $csvContent);

        Livewire::test(CsvImport::class)
            ->set('data.csv_file', [$filePath])
            ->call('importCsv')
            ->assertNotified('CSV Validation Failed');

        $this->assertEquals(0, Link::count());
    }

    public function test_handles_duplicate_custom_slug(): void
    {
        Link::factory()->create(['short_code' => 'existing-slug']);

        $csvContent = implode(',', CsvImportService::EXPECTED_COLUMNS)."\n";
        $csvContent .= 'https://example.com,existing-slug,,,,,,,';

        $filePath = 'csv-imports/duplicate.csv';
        Storage::disk('local')->put($filePath, $csvContent);

        Livewire::test(CsvImport::class)
            ->set('data.csv_file', [$filePath])
            ->call('importCsv')
            ->assertNotified('CSV Validation Failed');

        $this->assertEquals(1, Link::count()); // Only the existing link
    }

    public function test_requires_file_upload(): void
    {
        // When no file is uploaded, Filament's FileUpload validation catches it
        // as a required field error, or the importCsv method sends a notification
        Livewire::test(CsvImport::class)
            ->call('importCsv')
            ->assertHasFormErrors(['csv_file']);
    }

    public function test_handles_large_csv_with_background_processing(): void
    {
        // Create CSV with 150 rows (above threshold)
        $csvContent = implode(',', CsvImportService::EXPECTED_COLUMNS)."\n";
        for ($i = 1; $i <= 150; $i++) {
            $csvContent .= "https://example.com/page{$i},slug{$i},,,,,,,\n";
        }

        $filePath = 'csv-imports/large.csv';
        Storage::disk('local')->put($filePath, $csvContent);

        Livewire::test(CsvImport::class)
            ->set('data.csv_file', [$filePath])
            ->call('importCsv')
            ->assertNotified('Import Started')
            ->assertRedirect('/admin/links');
    }

    public function test_processes_small_csv_immediately(): void
    {
        // Create CSV with 2 rows (below threshold)
        $csvContent = implode(',', CsvImportService::EXPECTED_COLUMNS)."\n";
        $csvContent .= "https://example.com/page1,slug1,,,,,,,\n";
        $csvContent .= 'https://example.com/page2,slug2,,,,,,,';

        $filePath = 'csv-imports/small.csv';
        Storage::disk('local')->put($filePath, $csvContent);

        Livewire::test(CsvImport::class)
            ->set('data.csv_file', [$filePath])
            ->call('importCsv')
            ->assertNotified('Import Completed')
            ->assertRedirect('/admin/links');

        $this->assertEquals(2, Link::count());
    }

    public function test_creates_groups_automatically(): void
    {
        $csvContent = implode(',', CsvImportService::EXPECTED_COLUMNS)."\n";
        $csvContent .= "https://example.com/page1,slug1,Marketing,,,,,,\n";
        $csvContent .= 'https://example.com/page2,slug2,Sales,,,,,,';

        $filePath = 'csv-imports/groups.csv';
        Storage::disk('local')->put($filePath, $csvContent);

        Livewire::test(CsvImport::class)
            ->set('data.csv_file', [$filePath])
            ->call('importCsv')
            ->assertNotified('Import Completed');

        $this->assertDatabaseHas('link_groups', ['name' => 'Marketing']);
        $this->assertDatabaseHas('link_groups', ['name' => 'Sales']);

        $this->assertEquals(2, LinkGroup::count());
    }

    public function test_handles_partial_import_with_errors(): void
    {
        $csvContent = implode(',', CsvImportService::EXPECTED_COLUMNS)."\n";
        $csvContent .= "https://example.com/page1,slug1,,,,,,,\n"; // Valid
        $csvContent .= 'not-a-url,slug2,,,,,,,'; // Invalid URL

        $filePath = 'csv-imports/mixed.csv';
        Storage::disk('local')->put($filePath, $csvContent);

        Livewire::test(CsvImport::class)
            ->set('data.csv_file', [$filePath])
            ->call('importCsv')
            ->assertNotified('Import Completed')
            ->assertRedirect('/admin/links');

        // Only the valid link should be imported, invalid row is skipped
        $this->assertEquals(1, Link::count());
    }

    public function test_generates_short_codes_when_not_provided(): void
    {
        $csvContent = implode(',', CsvImportService::EXPECTED_COLUMNS)."\n";
        $csvContent .= 'https://example.com/page1,,,,,,,,'; // No custom slug

        $filePath = 'csv-imports/no-slug.csv';
        Storage::disk('local')->put($filePath, $csvContent);

        Livewire::test(CsvImport::class)
            ->set('data.csv_file', [$filePath])
            ->call('importCsv')
            ->assertNotified('Import Completed');

        $link = Link::first();
        $this->assertNotNull($link->short_code);
        $this->assertEquals(6, strlen($link->short_code));
    }

    public function test_handles_various_boolean_formats(): void
    {
        $csvContent = implode(',', CsvImportService::EXPECTED_COLUMNS)."\n";
        $csvContent .= "https://example.com/page1,slug1,,,,,,0,\n"; // is_active = false
        $csvContent .= 'https://example.com/page2,slug2,,,,,,1,'; // is_active = true

        $filePath = 'csv-imports/boolean.csv';
        Storage::disk('local')->put($filePath, $csvContent);

        Livewire::test(CsvImport::class)
            ->set('data.csv_file', [$filePath])
            ->call('importCsv')
            ->assertNotified('Import Completed');

        $inactiveLink = Link::where('short_code', 'slug1')->first();
        $activeLink = Link::where('short_code', 'slug2')->first();

        $this->assertFalse($inactiveLink->is_active);
        $this->assertTrue($activeLink->is_active);
    }

    public function test_cleans_up_uploaded_files(): void
    {
        $csvContent = implode(',', CsvImportService::EXPECTED_COLUMNS)."\n";
        $csvContent .= 'https://example.com,test-slug,,,,,,,';

        $filePath = 'csv-imports/cleanup.csv';
        Storage::disk('local')->put($filePath, $csvContent);

        $component = Livewire::test(CsvImport::class)
            ->set('data.csv_file', [$filePath])
            ->call('importCsv');

        // File should be cleaned up after import
        $this->assertEmpty($component->get('data.csv_file'));
    }
}
