<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\ReportComponent;
use App\Models\User;
use App\Services\ReportMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ReportBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Reset Spatie permission cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create report permissions
        $permissions = [
            'view_report', 'view_any_report', 'create_report',
            'update_report', 'delete_report',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
    }

    public function test_can_create_report()
    {
        $user = User::factory()->create();

        $report = Report::create([
            'name' => 'Test Report',
            'description' => 'A test report',
            'user_id' => $user->id,
            'visibility' => 'private',
            'is_active' => true,
        ]);

        $this->assertInstanceOf(Report::class, $report);
        $this->assertEquals('Test Report', $report->name);
        $this->assertEquals($user->id, $report->user_id);
    }

    public function test_can_add_components_to_report()
    {
        $user = User::factory()->create();
        $report = Report::factory()->create(['user_id' => $user->id]);

        $component = ReportComponent::create([
            'report_id' => $report->id,
            'component_type' => 'metric_card',
            'title' => 'Total Clicks',
            'config' => [
                'metric' => 'total_clicks',
                'show_comparison' => true,
            ],
            'position_x' => 0,
            'position_y' => 0,
            'width' => 4,
            'height' => 2,
            'order_index' => 0,
        ]);

        $this->assertInstanceOf(ReportComponent::class, $component);
        $this->assertEquals('metric_card', $component->component_type);
        $this->assertEquals($report->id, $component->report_id);
    }

    public function test_report_metrics_service_returns_data()
    {
        $service = new ReportMetricsService;

        $data = $service->getMetricData('total_clicks', [
            'show_comparison' => false,
        ], [
            'start_date' => now()->subDays(7)->toDateString(),
            'end_date' => now()->toDateString(),
        ]);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('value', $data);
        $this->assertArrayHasKey('label', $data);
    }

    public function test_report_visibility_permissions()
    {
        // Create users with proper permissions first
        $owner = User::factory()->create();
        $owner->givePermissionTo(['view_report', 'update_report', 'view_any_report']);

        $otherUser = User::factory()->create();
        $otherUser->givePermissionTo(['view_report']);

        $privateReport = Report::factory()->create([
            'user_id' => $owner->id,
            'visibility' => 'private',
        ]);

        $publicReport = Report::factory()->create([
            'user_id' => $owner->id,
            'visibility' => 'public',
        ]);

        // Owner can see their own private report
        $this->actingAs($owner);
        $this->assertTrue($privateReport->isVisible());
        $this->assertTrue($owner->can('update', $privateReport));

        // Other user cannot see private report but can see public reports
        $this->actingAs($otherUser);
        $this->assertFalse($privateReport->isVisible());
        $this->assertFalse($otherUser->can('update', $privateReport));

        // Other user can see public report but not edit
        $this->assertTrue($publicReport->isVisible());
        $this->assertFalse($otherUser->can('update', $publicReport));
    }

    public function test_date_range_attribute()
    {
        $report = Report::factory()->create([
            'global_filters' => [
                'date_range_type' => 'fixed',
                'start_date' => '2023-01-01',
                'end_date' => '2023-01-31',
            ],
        ]);

        $dateRange = $report->date_range;

        $this->assertEquals('2023-01-01', $dateRange['start_date']);
        $this->assertEquals('2023-01-31', $dateRange['end_date']);
    }
}
