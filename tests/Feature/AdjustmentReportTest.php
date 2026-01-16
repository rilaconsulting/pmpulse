<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Property;
use App\Models\PropertyAdjustment;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdjustmentReportTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $viewerUser;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::factory()->admin()->create();
        $viewerRole = Role::factory()->viewer()->create();

        $this->adminUser = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);
        $this->viewerUser = User::factory()->create([
            'role_id' => $viewerRole->id,
        ]);

        $this->property = Property::create([
            'external_id' => 'prop-1',
            'name' => 'Test Property',
            'is_active' => true,
            'unit_count' => 10,
            'total_sqft' => 10000,
        ]);
    }

    private function createAdjustment(array $attributes = []): PropertyAdjustment
    {
        return PropertyAdjustment::create(array_merge([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => 10,
            'adjusted_value' => 15,
            'effective_from' => Carbon::today()->subDays(5),
            'effective_to' => null,
            'reason' => 'Test adjustment',
            'created_by' => $this->adminUser->id,
        ], $attributes));
    }

    // ==================== Authorization Tests ====================

    public function test_guest_cannot_view_adjustments_report(): void
    {
        $response = $this->get('/admin/adjustments');

        $response->assertRedirect('/login');
    }

    public function test_non_admin_cannot_view_adjustments_report(): void
    {
        $response = $this->actingAs($this->viewerUser)->get('/admin/adjustments');

        $response->assertForbidden();
    }

    public function test_admin_can_view_adjustments_report(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/adjustments');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/AdjustmentsReport')
            ->has('adjustments')
            ->has('adjustableFields')
            ->has('creators')
            ->has('summary')
            ->has('filters')
        );
    }

    public function test_guest_cannot_export_adjustments(): void
    {
        $response = $this->get('/admin/adjustments/export');

        $response->assertRedirect('/login');
    }

    public function test_non_admin_cannot_export_adjustments(): void
    {
        $response = $this->actingAs($this->viewerUser)->get('/admin/adjustments/export');

        $response->assertForbidden();
    }

    // ==================== Index Page Data Tests ====================

    public function test_report_shows_all_adjustable_fields(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/adjustments');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('adjustableFields.unit_count')
            ->has('adjustableFields.total_sqft')
            ->has('adjustableFields.market_rent')
            ->has('adjustableFields.rentable_units')
        );
    }

    public function test_report_shows_adjustments_with_property_and_creator(): void
    {
        $this->createAdjustment();

        $response = $this->actingAs($this->adminUser)->get('/admin/adjustments');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('adjustments.data', 1)
            ->has('adjustments.data.0.property')
            ->has('adjustments.data.0.creator')
        );
    }

    public function test_report_shows_creators_who_have_adjustments(): void
    {
        $this->createAdjustment();

        $response = $this->actingAs($this->adminUser)->get('/admin/adjustments');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('creators', 1)
            ->where('creators.0.id', $this->adminUser->id)
        );
    }

    // ==================== Filtering Tests ====================

    public function test_default_status_is_active(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/adjustments');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('filters.status', 'active')
        );
    }

    public function test_filter_by_status_active(): void
    {
        // Create active adjustment (no end date)
        $activeAdjustment = $this->createAdjustment([
            'effective_from' => Carbon::today()->subDays(5),
            'effective_to' => null,
        ]);

        // Create historical adjustment (ended yesterday)
        $historicalAdjustment = $this->createAdjustment([
            'effective_from' => Carbon::today()->subDays(30),
            'effective_to' => Carbon::yesterday(),
        ]);

        $response = $this->actingAs($this->adminUser)->get('/admin/adjustments?status=active');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('adjustments.data', 1)
            ->where('adjustments.data.0.id', $activeAdjustment->id)
        );
    }

    public function test_filter_by_status_historical(): void
    {
        // Create active adjustment
        $this->createAdjustment([
            'effective_from' => Carbon::today()->subDays(5),
            'effective_to' => null,
        ]);

        // Create historical adjustment
        $historicalAdjustment = $this->createAdjustment([
            'effective_from' => Carbon::today()->subDays(30),
            'effective_to' => Carbon::yesterday(),
        ]);

        $response = $this->actingAs($this->adminUser)->get('/admin/adjustments?status=historical');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('adjustments.data', 1)
            ->where('adjustments.data.0.id', $historicalAdjustment->id)
        );
    }

    public function test_filter_by_status_all(): void
    {
        // Create active adjustment
        $this->createAdjustment([
            'effective_from' => Carbon::today()->subDays(5),
            'effective_to' => null,
        ]);

        // Create historical adjustment
        $this->createAdjustment([
            'effective_from' => Carbon::today()->subDays(30),
            'effective_to' => Carbon::yesterday(),
        ]);

        $response = $this->actingAs($this->adminUser)->get('/admin/adjustments?status=all');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('adjustments.data', 2)
        );
    }

    public function test_filter_by_field_name(): void
    {
        $unitCountAdjustment = $this->createAdjustment([
            'field_name' => 'unit_count',
        ]);

        $sqftAdjustment = $this->createAdjustment([
            'field_name' => 'total_sqft',
        ]);

        $response = $this->actingAs($this->adminUser)->get('/admin/adjustments?field=unit_count&status=all');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('adjustments.data', 1)
            ->where('adjustments.data.0.id', $unitCountAdjustment->id)
        );
    }

    public function test_filter_by_creator(): void
    {
        $secondAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'admin')->first()->id,
        ]);

        $adminAdjustment = $this->createAdjustment([
            'created_by' => $this->adminUser->id,
        ]);

        $secondAdminAdjustment = $this->createAdjustment([
            'created_by' => $secondAdmin->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get('/admin/adjustments?creator='.$this->adminUser->id.'&status=all');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('adjustments.data', 1)
            ->where('adjustments.data.0.id', $adminAdjustment->id)
        );
    }

    public function test_filter_by_date_range(): void
    {
        $olderAdjustment = $this->createAdjustment([
            'effective_from' => Carbon::today()->subDays(30),
        ]);

        $newerAdjustment = $this->createAdjustment([
            'effective_from' => Carbon::today()->subDays(5),
        ]);

        $from = Carbon::today()->subDays(10)->format('Y-m-d');
        $to = Carbon::today()->format('Y-m-d');

        $response = $this->actingAs($this->adminUser)
            ->get("/admin/adjustments?from={$from}&to={$to}&status=all");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('adjustments.data', 1)
            ->where('adjustments.data.0.id', $newerAdjustment->id)
        );
    }

    // ==================== Summary Statistics Tests ====================

    public function test_summary_shows_total_count(): void
    {
        $this->createAdjustment();
        $this->createAdjustment();

        $response = $this->actingAs($this->adminUser)->get('/admin/adjustments?status=all');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('summary.total', 2)
        );
    }

    public function test_summary_shows_count_by_field(): void
    {
        $this->createAdjustment(['field_name' => 'unit_count']);
        $this->createAdjustment(['field_name' => 'unit_count']);
        $this->createAdjustment(['field_name' => 'total_sqft']);

        $response = $this->actingAs($this->adminUser)->get('/admin/adjustments?status=all');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('summary.by_field.unit_count', 2)
            ->where('summary.by_field.total_sqft', 1)
        );
    }

    public function test_summary_shows_properties_affected(): void
    {
        $secondProperty = Property::create([
            'external_id' => 'prop-2',
            'name' => 'Second Property',
            'is_active' => true,
        ]);

        // Two adjustments for same property
        $this->createAdjustment(['property_id' => $this->property->id]);
        $this->createAdjustment(['property_id' => $this->property->id]);
        // One adjustment for different property
        $this->createAdjustment(['property_id' => $secondProperty->id]);

        $response = $this->actingAs($this->adminUser)->get('/admin/adjustments?status=all');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('summary.properties_affected', 2)
        );
    }

    // ==================== Pagination Tests ====================

    public function test_pagination_works_correctly(): void
    {
        // Create 30 adjustments (more than 25 per page)
        for ($i = 0; $i < 30; $i++) {
            $this->createAdjustment();
        }

        $response = $this->actingAs($this->adminUser)->get('/admin/adjustments?status=all');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('adjustments.data', 25)
            ->where('adjustments.total', 30)
            ->has('adjustments.next_page_url')
        );
    }

    public function test_pagination_preserves_query_string(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->createAdjustment();
        }

        $response = $this->actingAs($this->adminUser)
            ->get('/admin/adjustments?status=all&field=unit_count');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('filters.status', 'all')
            ->where('filters.field', 'unit_count')
        );
    }

    // ==================== CSV Export Tests ====================

    public function test_admin_can_export_adjustments_to_csv(): void
    {
        $this->createAdjustment();

        $response = $this->actingAs($this->adminUser)->get('/admin/adjustments/export');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');
    }

    public function test_csv_export_has_correct_headers(): void
    {
        $this->createAdjustment();

        $response = $this->actingAs($this->adminUser)->get('/admin/adjustments/export');

        $content = $response->streamedContent();
        $lines = explode("\n", $content);
        $headers = str_getcsv($lines[0]);

        $this->assertEquals([
            'Property',
            'Field',
            'Original Value',
            'Adjusted Value',
            'Effective From',
            'Effective To',
            'Reason',
            'Created By',
            'Created At',
        ], $headers);
    }

    public function test_csv_export_contains_adjustment_data(): void
    {
        $adjustment = $this->createAdjustment([
            'field_name' => 'unit_count',
            'original_value' => 10,
            'adjusted_value' => 15,
            'reason' => 'Test reason',
        ]);

        $response = $this->actingAs($this->adminUser)->get('/admin/adjustments/export');

        $content = $response->streamedContent();
        $lines = explode("\n", $content);
        $data = str_getcsv($lines[1]);

        $this->assertEquals('Test Property', $data[0]);
        $this->assertEquals('Unit Count', $data[1]);
        $this->assertEquals('10', $data[2]);
        $this->assertEquals('15', $data[3]);
        $this->assertEquals('Test reason', $data[6]);
        $this->assertEquals($this->adminUser->name, $data[7]);
    }

    public function test_csv_export_shows_permanent_for_no_end_date(): void
    {
        $this->createAdjustment(['effective_to' => null]);

        $response = $this->actingAs($this->adminUser)->get('/admin/adjustments/export');

        $content = $response->streamedContent();
        $lines = explode("\n", $content);
        $data = str_getcsv($lines[1]);

        $this->assertEquals('Permanent', $data[5]);
    }

    public function test_csv_export_sanitizes_formula_injection(): void
    {
        $this->createAdjustment([
            'reason' => '=HYPERLINK("http://evil.com", "Click")',
        ]);

        $response = $this->actingAs($this->adminUser)->get('/admin/adjustments/export');

        $content = $response->streamedContent();
        $lines = explode("\n", $content);
        $data = str_getcsv($lines[1]);

        // Reason should be prefixed with single quote
        $this->assertStringStartsWith("'=", $data[6]);
    }

    public function test_csv_export_sanitizes_plus_prefix(): void
    {
        $this->createAdjustment([
            'reason' => '+1234567890',
        ]);

        $response = $this->actingAs($this->adminUser)->get('/admin/adjustments/export');

        $content = $response->streamedContent();
        $lines = explode("\n", $content);
        $data = str_getcsv($lines[1]);

        $this->assertStringStartsWith("'+", $data[6]);
    }

    public function test_csv_export_sanitizes_minus_prefix(): void
    {
        $this->createAdjustment([
            'reason' => '-1234567890',
        ]);

        $response = $this->actingAs($this->adminUser)->get('/admin/adjustments/export');

        $content = $response->streamedContent();
        $lines = explode("\n", $content);
        $data = str_getcsv($lines[1]);

        $this->assertStringStartsWith("'-", $data[6]);
    }

    public function test_csv_export_sanitizes_at_prefix(): void
    {
        $this->createAdjustment([
            'reason' => '@SUM(A1:A10)',
        ]);

        $response = $this->actingAs($this->adminUser)->get('/admin/adjustments/export');

        $content = $response->streamedContent();
        $lines = explode("\n", $content);
        $data = str_getcsv($lines[1]);

        $this->assertStringStartsWith("'@", $data[6]);
    }

    public function test_csv_export_respects_filters(): void
    {
        $unitCountAdjustment = $this->createAdjustment(['field_name' => 'unit_count']);
        $sqftAdjustment = $this->createAdjustment(['field_name' => 'total_sqft']);

        $response = $this->actingAs($this->adminUser)
            ->get('/admin/adjustments/export?field=unit_count&status=all');

        $content = $response->streamedContent();
        $lines = array_filter(explode("\n", $content)); // Remove empty lines

        // Should have header + 1 data row
        $this->assertCount(2, $lines);
    }

    public function test_csv_export_empty_result_set(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/adjustments/export');

        $content = $response->streamedContent();
        $lines = array_filter(explode("\n", $content));

        // Should only have header row
        $this->assertCount(1, $lines);
    }

    // ==================== Empty State Tests ====================

    public function test_empty_adjustments_handled_correctly(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/adjustments');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('adjustments.data', 0)
            ->where('summary.total', 0)
            ->where('summary.properties_affected', 0)
        );
    }

    public function test_empty_creators_list_when_no_adjustments(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/adjustments');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('creators', 0)
        );
    }

    // ==================== Validation Tests ====================

    public function test_invalid_status_uses_default(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get('/admin/adjustments?status=invalid');

        $response->assertSessionHasErrors('status');
    }

    public function test_invalid_date_format_fails_validation(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get('/admin/adjustments?from=not-a-date');

        $response->assertSessionHasErrors('from');
    }
}
