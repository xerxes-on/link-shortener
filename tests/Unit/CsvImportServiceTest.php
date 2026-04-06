<?php

namespace Tests\Unit;

use App\Models\Link;
use App\Models\LinkGroup;
use App\Models\User;
use App\Services\CsvImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsvImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private CsvImportService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CsvImportService;
        $this->user = User::factory()->create();
    }

    public function test_can_get_sample_csv(): void
    {
        $csv = CsvImportService::getSampleCsv();

        $this->assertStringContainsString('original_url,custom_slug,group_name', $csv);
        $this->assertStringContainsString('https://github.com/laravel/laravel', $csv);
    }

    public function test_validates_csv_header(): void
    {
        $csvContent = "wrong,header,format\nhttps://example.com,,";

        $result = $this->service->parseAndValidate($csvContent, $this->user);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid CSV header', $result['errors'][0]);
    }

    public function test_parses_valid_csv(): void
    {
        $csvContent = implode(',', CsvImportService::EXPECTED_COLUMNS)."\n";
        $csvContent .= 'https://example.com,test-slug,Test Group,2024-12-31,secret,100,302,1,Test note';

        $result = $this->service->parseAndValidate($csvContent, $this->user);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['total_rows']);
        $this->assertEquals(1, $result['valid_rows']);
        $this->assertEmpty($result['errors']);
    }

    public function test_validates_required_fields(): void
    {
        $csvContent = implode(',', CsvImportService::EXPECTED_COLUMNS)."\n";
        // Use a row with at least one non-empty field so it isn't filtered out entirely
        $csvContent .= ',test-slug,,,,,,';

        $result = $this->service->parseAndValidate($csvContent, $this->user);

        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertStringContainsString('is required', $result['warnings'][0]);
    }

    public function test_validates_url_format(): void
    {
        $csvContent = implode(',', CsvImportService::EXPECTED_COLUMNS)."\n";
        $csvContent .= 'not-a-url,,,,,,,';

        $result = $this->service->parseAndValidate($csvContent, $this->user);

        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertStringContainsString('must be a valid URL', $result['warnings'][0]);
    }

    public function test_validates_custom_slug_format(): void
    {
        $csvContent = implode(',', CsvImportService::EXPECTED_COLUMNS)."\n";
        $csvContent .= 'https://example.com,invalid slug!,,,,,,,';

        $result = $this->service->parseAndValidate($csvContent, $this->user);

        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertStringContainsString('format is invalid', $result['warnings'][0]);
    }

    public function test_validates_unique_custom_slug(): void
    {
        Link::factory()->create(['short_code' => 'existing-slug']);

        $csvContent = implode(',', CsvImportService::EXPECTED_COLUMNS)."\n";
        $csvContent .= 'https://example.com,existing-slug,,,,,,,';

        $result = $this->service->parseAndValidate($csvContent, $this->user);

        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertStringContainsString('already exists', $result['warnings'][0]);
    }

    public function test_validates_redirect_type(): void
    {
        $csvContent = implode(',', CsvImportService::EXPECTED_COLUMNS)."\n";
        $csvContent .= 'https://example.com,,,,,,999,,';

        $result = $this->service->parseAndValidate($csvContent, $this->user);

        // Invalid redirect_type is auto-corrected to 302 with a warning, row is still valid
        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertStringContainsString("Invalid redirect_type '999' changed to 302", $result['warnings'][0]);
    }

    public function test_validates_expiration_date(): void
    {
        $csvContent = implode(',', CsvImportService::EXPECTED_COLUMNS)."\n";
        $csvContent .= 'https://example.com,,,invalid-date,,,,';

        $result = $this->service->parseAndValidate($csvContent, $this->user);

        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertStringContainsString('must be a valid date', $result['warnings'][0]);
    }

    public function test_imports_valid_data(): void
    {
        $csvContent = implode(',', CsvImportService::EXPECTED_COLUMNS)."\n";
        $csvContent .= 'https://example.com,test-slug,Test Group,2024-12-31,secret,100,302,1,Test note';

        $parseResult = $this->service->parseAndValidate($csvContent, $this->user);
        $importResult = $this->service->import($parseResult['data'], $this->user);

        $this->assertTrue($importResult['success']);
        $this->assertEquals(1, $importResult['imported']);
        $this->assertEquals(1, $importResult['total']);
        $this->assertEmpty($importResult['errors']);

        $link = Link::where('short_code', 'test-slug')->first();
        $this->assertNotNull($link);
        $this->assertEquals('https://example.com', $link->original_url);
        $this->assertEquals('secret', $link->password);
        $this->assertEquals(100, $link->click_limit);
        $this->assertEquals(302, $link->redirect_type);
        $this->assertTrue($link->is_active);
    }

    public function test_creates_group_if_not_exists(): void
    {
        $csvContent = implode(',', CsvImportService::EXPECTED_COLUMNS)."\n";
        $csvContent .= 'https://example.com,,New Group,,,,,';

        $parseResult = $this->service->parseAndValidate($csvContent, $this->user);
        $importResult = $this->service->import($parseResult['data'], $this->user);

        $this->assertTrue($importResult['success']);

        $group = LinkGroup::where('name', 'New Group')->first();
        $this->assertNotNull($group);

        $link = Link::first();
        $this->assertEquals($group->id, $link->group_id);
    }

    public function test_generates_short_code_when_not_provided(): void
    {
        $csvContent = implode(',', CsvImportService::EXPECTED_COLUMNS)."\n";
        $csvContent .= 'https://example.com,,,,,,,,';

        $parseResult = $this->service->parseAndValidate($csvContent, $this->user);
        $importResult = $this->service->import($parseResult['data'], $this->user);

        $this->assertTrue($importResult['success']);

        $link = Link::first();
        $this->assertNotNull($link->short_code);
        $this->assertEquals(6, strlen($link->short_code));
    }

    public function test_handles_boolean_values(): void
    {
        $csvContent = implode(',', CsvImportService::EXPECTED_COLUMNS)."\n";
        $csvContent .= 'https://example.com,,,,,,,0,'; // is_active = 0

        $parseResult = $this->service->parseAndValidate($csvContent, $this->user);
        $importResult = $this->service->import($parseResult['data'], $this->user);

        $this->assertTrue($importResult['success']);

        $link = Link::first();
        $this->assertFalse($link->is_active);
    }

    public function test_handles_empty_csv(): void
    {
        $result = $this->service->parseAndValidate('', $this->user);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('CSV file is empty', $result['errors'][0]);
    }

    public function test_handles_csv_with_only_header(): void
    {
        $csvContent = implode(',', CsvImportService::EXPECTED_COLUMNS);

        $result = $this->service->parseAndValidate($csvContent, $this->user);

        // No data rows means no valid data, so success is false
        $this->assertFalse($result['success']);
        $this->assertEquals(0, $result['total_rows']);
        $this->assertEquals(0, $result['valid_rows']);
    }

    public function test_handles_mixed_valid_invalid_rows(): void
    {
        $csvContent = implode(',', CsvImportService::EXPECTED_COLUMNS)."\n";
        $csvContent .= "https://example.com/1,slug1,,,,,,,\n"; // Valid
        $csvContent .= "not-a-url,slug2,,,,,,,\n"; // Invalid URL
        $csvContent .= 'https://example.com/3,slug3,,,,,,,'; // Valid

        $result = $this->service->parseAndValidate($csvContent, $this->user);

        // Success is true because there are valid rows
        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['total_rows']);
        $this->assertEquals(2, $result['valid_rows']);
        // Validation errors for individual rows go to warnings
        $this->assertNotEmpty($result['warnings']);
        $this->assertEmpty($result['errors']);
    }

    public function test_handles_case_insensitive_headers(): void
    {
        $headers = array_map('strtoupper', CsvImportService::EXPECTED_COLUMNS);
        $csvContent = implode(',', $headers)."\n";
        $csvContent .= 'https://example.com,test-slug,,,,,,,';

        $result = $this->service->parseAndValidate($csvContent, $this->user);

        $this->assertTrue($result['success']);
    }

    public function test_trims_whitespace_from_values(): void
    {
        $csvContent = implode(',', CsvImportService::EXPECTED_COLUMNS)."\n";
        $csvContent .= ' https://example.com , test-slug , Test Group ,,,,,';

        $parseResult = $this->service->parseAndValidate($csvContent, $this->user);
        $importResult = $this->service->import($parseResult['data'], $this->user);

        $this->assertTrue($importResult['success']);

        $link = Link::where('short_code', 'test-slug')->first();
        $this->assertEquals('https://example.com', $link->original_url);

        $group = LinkGroup::where('name', 'Test Group')->first();
        $this->assertNotNull($group);
    }

    public function test_applies_smart_defaults(): void
    {
        // Create a default group
        $defaultGroup = LinkGroup::create([
            'name' => 'Default Group',
            'color' => '#3B82F6',
            'is_default' => true,
        ]);

        $csvContent = implode(',', CsvImportService::EXPECTED_COLUMNS)."\n";
        // Test empty values that should get defaults
        $csvContent .= 'https://example.com/test1,,,,,,,,"Test with defaults"'."\n";
        // Test invalid redirect_type that should default to 302
        $csvContent .= 'https://example.com/test2,,,,,,999,,"Invalid redirect type"';

        $parseResult = $this->service->parseAndValidate($csvContent, $this->user);
        $this->assertTrue($parseResult['success']);
        $this->assertStringContainsString('Invalid redirect_type \'999\' changed to 302', implode(' ', $parseResult['warnings']));

        $importResult = $this->service->import($parseResult['data'], $this->user);
        $this->assertTrue($importResult['success']);
        $this->assertEquals(2, $importResult['imported']);

        // Check that links got default values
        $links = Link::whereIn('original_url', ['https://example.com/test1', 'https://example.com/test2'])->get();

        foreach ($links as $link) {
            $this->assertEquals($defaultGroup->id, $link->group_id, 'Link should be assigned to default group');
            $this->assertEquals(302, $link->redirect_type, 'Redirect type should default to 302');
            $this->assertTrue($link->is_active, 'Link should default to active');
        }
    }
}
