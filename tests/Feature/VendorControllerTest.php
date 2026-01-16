<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->create(['name' => 'viewer']);
        $adminRole = Role::factory()->admin()->create();

        $this->user = User::factory()->create(['role_id' => $role->id]);
        $this->adminUser = User::factory()->create(['role_id' => $adminRole->id]);
    }

    /**
     * Check if the current database driver is PostgreSQL.
     */
    protected function isPostgres(): bool
    {
        return config('database.default') === 'pgsql';
    }

    /**
     * Skip test if not using PostgreSQL (for tests using PostgreSQL-specific features).
     */
    protected function skipIfNotPostgres(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('This test requires PostgreSQL for ILIKE queries.');
        }
    }

    // ==================== Guest Access Tests ====================

    public function test_guest_cannot_access_vendor_index(): void
    {
        $response = $this->get('/vendors');

        $response->assertRedirect('/login');
    }

    public function test_guest_cannot_access_vendor_show(): void
    {
        $vendor = Vendor::factory()->create();

        $response = $this->get("/vendors/{$vendor->id}");

        $response->assertRedirect('/login');
    }

    public function test_guest_cannot_access_compliance_page(): void
    {
        $response = $this->get('/vendors/compliance');

        $response->assertRedirect('/login');
    }

    public function test_guest_cannot_access_compare_page(): void
    {
        $response = $this->get('/vendors/compare');

        $response->assertRedirect('/login');
    }

    public function test_guest_cannot_access_deduplication_page(): void
    {
        $response = $this->get('/vendors/deduplication');

        $response->assertRedirect('/login');
    }

    // ==================== Index Page Tests ====================

    public function test_authenticated_user_can_access_vendor_index(): void
    {
        $this->skipIfNotPostgres();

        $response = $this->actingAs($this->user)->get('/vendors');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Vendors/Index')
            ->has('vendors')
            ->has('trades')
            ->has('vendorTypes')
            ->has('stats')
            ->has('filters')
        );
    }

    public function test_vendor_index_shows_vendors(): void
    {
        $this->skipIfNotPostgres();

        $vendor = Vendor::factory()->create([
            'company_name' => 'Test Vendor Company',
        ]);

        $response = $this->actingAs($this->user)->get('/vendors');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('vendors.data', 1)
            ->where('vendors.data.0.company_name', 'Test Vendor Company')
        );
    }

    public function test_vendor_index_search_by_name(): void
    {
        $this->skipIfNotPostgres();

        Vendor::factory()->create(['company_name' => 'Alpha Plumbing']);
        Vendor::factory()->create(['company_name' => 'Beta Electric']);

        $response = $this->actingAs($this->user)->get('/vendors?search=Alpha');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('vendors.data', 1)
            ->where('vendors.data.0.company_name', 'Alpha Plumbing')
        );
    }

    public function test_vendor_index_search_by_contact_name(): void
    {
        $this->skipIfNotPostgres();

        Vendor::factory()->create([
            'company_name' => 'Company A',
            'contact_name' => 'John Smith',
            'email' => 'alpha@example.com',
        ]);
        Vendor::factory()->create([
            'company_name' => 'Company B',
            'contact_name' => 'Jane Doe',
            'email' => 'beta@example.com',
        ]);

        $response = $this->actingAs($this->user)->get('/vendors?search=John');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('vendors.data', 1)
            ->where('vendors.data.0.contact_name', 'John Smith')
        );
    }

    public function test_vendor_index_search_by_email(): void
    {
        $this->skipIfNotPostgres();

        Vendor::factory()->create([
            'company_name' => 'Company A',
            'email' => 'alpha@example.com',
        ]);
        Vendor::factory()->create([
            'company_name' => 'Company B',
            'email' => 'beta@example.com',
        ]);

        $response = $this->actingAs($this->user)->get('/vendors?search=alpha@');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('vendors.data', 1)
            ->where('vendors.data.0.email', 'alpha@example.com')
        );
    }

    public function test_vendor_index_filter_by_trade(): void
    {
        $this->skipIfNotPostgres();

        Vendor::factory()->withTrade('Plumbing')->create(['company_name' => 'Plumber Co']);
        Vendor::factory()->withTrade('Electrical')->create(['company_name' => 'Electric Co']);

        $response = $this->actingAs($this->user)->get('/vendors?trade=Plumbing');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('vendors.data', 1)
            ->where('vendors.data.0.company_name', 'Plumber Co')
        );
    }

    public function test_vendor_index_filter_by_active_status(): void
    {
        $this->skipIfNotPostgres();

        Vendor::factory()->create(['company_name' => 'Active Vendor', 'is_active' => true]);
        Vendor::factory()->inactive()->create(['company_name' => 'Inactive Vendor']);

        $response = $this->actingAs($this->user)->get('/vendors?is_active=1');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('vendors.data', 1)
            ->where('vendors.data.0.company_name', 'Active Vendor')
        );
    }

    public function test_vendor_index_filter_by_inactive_status(): void
    {
        $this->skipIfNotPostgres();

        Vendor::factory()->create(['company_name' => 'Active Vendor', 'is_active' => true]);
        Vendor::factory()->inactive()->create(['company_name' => 'Inactive Vendor']);

        $response = $this->actingAs($this->user)->get('/vendors?is_active=0');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('vendors.data', 1)
            ->where('vendors.data.0.company_name', 'Inactive Vendor')
        );
    }

    public function test_vendor_index_filter_by_expired_insurance(): void
    {
        $this->skipIfNotPostgres();

        Vendor::factory()->create([
            'company_name' => 'Current Vendor',
            'workers_comp_expires' => now()->addMonths(6),
            'liability_ins_expires' => now()->addMonths(6),
        ]);
        Vendor::factory()->withExpiredInsurance()->create([
            'company_name' => 'Expired Vendor',
        ]);

        $response = $this->actingAs($this->user)->get('/vendors?insurance_status=expired');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('vendors.data', 1)
            ->where('vendors.data.0.company_name', 'Expired Vendor')
        );
    }

    public function test_vendor_index_filter_by_expiring_soon_insurance(): void
    {
        $this->skipIfNotPostgres();

        Vendor::factory()->create([
            'company_name' => 'Current Vendor',
            'workers_comp_expires' => now()->addMonths(6),
            'liability_ins_expires' => now()->addMonths(6),
        ]);
        Vendor::factory()->create([
            'company_name' => 'Expiring Vendor',
            'workers_comp_expires' => now()->addDays(15),
            'liability_ins_expires' => now()->addMonths(6),
        ]);

        $response = $this->actingAs($this->user)->get('/vendors?insurance_status=expiring_soon');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('vendors.data', 1)
            ->where('vendors.data.0.company_name', 'Expiring Vendor')
        );
    }

    public function test_vendor_index_filter_by_current_insurance(): void
    {
        $this->skipIfNotPostgres();

        Vendor::factory()->create([
            'company_name' => 'Current Vendor',
            'workers_comp_expires' => now()->addMonths(6),
            'liability_ins_expires' => now()->addMonths(6),
            'auto_ins_expires' => null,
        ]);
        Vendor::factory()->withExpiredInsurance()->create([
            'company_name' => 'Expired Vendor',
        ]);

        $response = $this->actingAs($this->user)->get('/vendors?insurance_status=current');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('vendors.data', 1)
            ->where('vendors.data.0.company_name', 'Current Vendor')
        );
    }

    public function test_vendor_index_sorts_by_company_name_ascending(): void
    {
        $this->skipIfNotPostgres();

        Vendor::factory()->create(['company_name' => 'Zebra Plumbing']);
        Vendor::factory()->create(['company_name' => 'Alpha Electric']);

        $response = $this->actingAs($this->user)->get('/vendors?sort=company_name&direction=asc');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('vendors.data', 2)
            ->where('vendors.data.0.company_name', 'Alpha Electric')
            ->where('vendors.data.1.company_name', 'Zebra Plumbing')
        );
    }

    public function test_vendor_index_sorts_by_company_name_descending(): void
    {
        $this->skipIfNotPostgres();

        Vendor::factory()->create(['company_name' => 'Alpha Electric']);
        Vendor::factory()->create(['company_name' => 'Zebra Plumbing']);

        $response = $this->actingAs($this->user)->get('/vendors?sort=company_name&direction=desc');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('vendors.data', 2)
            ->where('vendors.data.0.company_name', 'Zebra Plumbing')
            ->where('vendors.data.1.company_name', 'Alpha Electric')
        );
    }

    public function test_vendor_index_sorts_by_work_orders_count(): void
    {
        $this->skipIfNotPostgres();

        $vendor1 = Vendor::factory()->create(['company_name' => 'Few WOs']);
        $vendor2 = Vendor::factory()->create(['company_name' => 'Many WOs']);

        WorkOrder::factory()->count(2)->forVendor($vendor1)->create();
        WorkOrder::factory()->count(5)->forVendor($vendor2)->create();

        $response = $this->actingAs($this->user)->get('/vendors?sort=work_orders_count&direction=desc');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('vendors.data', 2)
            ->where('vendors.data.0.company_name', 'Many WOs')
        );
    }

    public function test_vendor_index_sorts_by_active_status(): void
    {
        $this->skipIfNotPostgres();

        Vendor::factory()->inactive()->create(['company_name' => 'Inactive']);
        Vendor::factory()->create(['company_name' => 'Active', 'is_active' => true]);

        $response = $this->actingAs($this->user)->get('/vendors?sort=is_active&direction=desc');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('vendors.data', 2)
            ->where('vendors.data.0.company_name', 'Active')
        );
    }

    public function test_vendor_index_paginates_results(): void
    {
        $this->skipIfNotPostgres();

        Vendor::factory()->count(20)->create();

        $response = $this->actingAs($this->user)->get('/vendors');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('vendors.data', 15) // Default pagination is 15
            ->where('vendors.total', 20)
        );
    }

    public function test_vendor_index_shows_only_canonical_by_default(): void
    {
        $this->skipIfNotPostgres();

        $canonical = Vendor::factory()->create(['company_name' => 'Canonical Vendor']);
        Vendor::factory()->duplicateOf($canonical)->create(['company_name' => 'Duplicate Vendor']);

        $response = $this->actingAs($this->user)->get('/vendors');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('vendors.data', 1)
            ->where('vendors.data.0.company_name', 'Canonical Vendor')
        );
    }

    public function test_vendor_index_shows_all_vendors_when_filtered(): void
    {
        $this->skipIfNotPostgres();

        $canonical = Vendor::factory()->create(['company_name' => 'Canonical Vendor']);
        Vendor::factory()->duplicateOf($canonical)->create(['company_name' => 'Duplicate Vendor']);

        $response = $this->actingAs($this->user)->get('/vendors?canonical_filter=all');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('vendors.data', 2)
        );
    }

    public function test_vendor_index_shows_stats(): void
    {
        $this->skipIfNotPostgres();

        Vendor::factory()->count(3)->create(['is_active' => true]);
        Vendor::factory()->inactive()->create();
        Vendor::factory()->withExpiredInsurance()->create(['is_active' => true]);

        $response = $this->actingAs($this->user)->get('/vendors');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('stats.total_vendors')
            ->has('stats.active_vendors')
            ->has('stats.expired_insurance')
            ->has('stats.portfolio_stats')
        );
    }

    // ==================== Show Page Tests ====================

    public function test_authenticated_user_can_view_vendor_details(): void
    {
        $this->skipIfNotPostgres();

        $vendor = Vendor::factory()->create();

        $response = $this->actingAs($this->user)->get("/vendors/{$vendor->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Vendors/Show')
            ->has('vendor')
            ->has('metrics')
            ->has('periodComparison')
            ->has('tradeAnalysis')
            ->has('responseMetrics')
            ->has('spendTrend')
            ->has('insuranceStatus')
            ->has('workOrders')
            ->has('workOrderStats')
        );
    }

    public function test_vendor_show_displays_vendor_data(): void
    {
        $this->skipIfNotPostgres();

        $vendor = Vendor::factory()->create([
            'company_name' => 'Test Plumbing Co',
            'contact_name' => 'John Doe',
            'email' => 'john@test.com',
        ]);

        $response = $this->actingAs($this->user)->get("/vendors/{$vendor->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('vendor.company_name', 'Test Plumbing Co')
            ->where('vendor.contact_name', 'John Doe')
            ->where('vendor.email', 'john@test.com')
        );
    }

    public function test_vendor_show_includes_work_orders(): void
    {
        $this->skipIfNotPostgres();

        $vendor = Vendor::factory()->create();
        WorkOrder::factory()->count(5)->forVendor($vendor)->create();

        $response = $this->actingAs($this->user)->get("/vendors/{$vendor->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('workOrders.data', 5)
            ->where('workOrderStats.total', 5)
        );
    }

    public function test_vendor_show_filters_work_orders_by_status(): void
    {
        $this->skipIfNotPostgres();

        $vendor = Vendor::factory()->create();
        WorkOrder::factory()->count(3)->forVendor($vendor)->completed()->create();
        WorkOrder::factory()->count(2)->forVendor($vendor)->create(); // Open

        $response = $this->actingAs($this->user)->get("/vendors/{$vendor->id}?wo_status=completed");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('workOrders.data', 3)
        );
    }

    public function test_vendor_show_returns_404_for_nonexistent_vendor(): void
    {
        $response = $this->actingAs($this->user)->get('/vendors/nonexistent-id');

        $response->assertStatus(404);
    }

    // ==================== Compliance Page Tests ====================

    public function test_authenticated_user_can_access_compliance_page(): void
    {
        $response = $this->actingAs($this->user)->get('/vendors/compliance');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Vendors/Compliance')
            ->has('expired')
            ->has('expiringSoon')
            ->has('expiringQuarter')
            ->has('missingInfo')
            ->has('compliant')
            ->has('doNotUse')
            ->has('workersCompIssues')
            ->has('stats')
        );
    }

    public function test_compliance_page_categorizes_expired_vendors(): void
    {
        Vendor::factory()->create([
            'company_name' => 'Expired Vendor',
            'workers_comp_expires' => now()->subDays(10),
            'liability_ins_expires' => now()->addMonths(6),
            'is_active' => true,
            'do_not_use' => false,
        ]);
        Vendor::factory()->create([
            'company_name' => 'Current Vendor',
            'workers_comp_expires' => now()->addMonths(6),
            'liability_ins_expires' => now()->addMonths(6),
            'auto_ins_expires' => now()->addMonths(6), // All three required for compliant
            'is_active' => true,
            'do_not_use' => false,
        ]);

        $response = $this->actingAs($this->user)->get('/vendors/compliance');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('expired', 1)
            ->where('expired.0.vendor.company_name', 'Expired Vendor')
            ->has('compliant', 1)
        );
    }

    public function test_compliance_page_categorizes_expiring_soon_vendors(): void
    {
        Vendor::factory()->create([
            'company_name' => 'Expiring Vendor',
            'workers_comp_expires' => now()->addDays(15),
            'liability_ins_expires' => now()->addMonths(6),
            'is_active' => true,
            'do_not_use' => false,
        ]);

        $response = $this->actingAs($this->user)->get('/vendors/compliance');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('expiringSoon', 1)
            ->where('expiringSoon.0.vendor.company_name', 'Expiring Vendor')
        );
    }

    public function test_compliance_page_categorizes_expiring_quarter_vendors(): void
    {
        Vendor::factory()->create([
            'company_name' => 'Quarter Expiring',
            'workers_comp_expires' => now()->addDays(60),
            'liability_ins_expires' => now()->addMonths(6),
            'is_active' => true,
            'do_not_use' => false,
        ]);

        $response = $this->actingAs($this->user)->get('/vendors/compliance');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('expiringQuarter', 1)
            ->where('expiringQuarter.0.vendor.company_name', 'Quarter Expiring')
        );
    }

    public function test_compliance_page_shows_do_not_use_vendors(): void
    {
        Vendor::factory()->doNotUse()->create([
            'company_name' => 'Banned Vendor',
        ]);

        $response = $this->actingAs($this->user)->get('/vendors/compliance');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('doNotUse', 1)
            ->where('doNotUse.0.company_name', 'Banned Vendor')
        );
    }

    public function test_compliance_page_workers_comp_section(): void
    {
        // Expired workers comp
        Vendor::factory()->create([
            'company_name' => 'WC Expired',
            'workers_comp_expires' => now()->subDays(10),
            'liability_ins_expires' => now()->addMonths(6),
            'is_active' => true,
            'do_not_use' => false,
        ]);
        // Expiring workers comp
        Vendor::factory()->create([
            'company_name' => 'WC Expiring',
            'workers_comp_expires' => now()->addDays(15),
            'liability_ins_expires' => now()->addMonths(6),
            'is_active' => true,
            'do_not_use' => false,
        ]);
        // Missing workers comp
        Vendor::factory()->create([
            'company_name' => 'WC Missing',
            'workers_comp_expires' => null,
            'liability_ins_expires' => now()->addMonths(6),
            'is_active' => true,
            'do_not_use' => false,
        ]);

        $response = $this->actingAs($this->user)->get('/vendors/compliance');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('workersCompIssues.expired', 1)
            ->has('workersCompIssues.expiring_soon', 1)
            ->has('workersCompIssues.missing', 1)
        );
    }

    public function test_compliance_page_stats_summary(): void
    {
        // Create various vendor states
        Vendor::factory()->create([
            'is_active' => true,
            'do_not_use' => false,
            'workers_comp_expires' => now()->addMonths(6),
            'liability_ins_expires' => now()->addMonths(6),
        ]);
        Vendor::factory()->withExpiredInsurance()->create([
            'is_active' => true,
            'do_not_use' => false,
        ]);
        Vendor::factory()->doNotUse()->create();

        $response = $this->actingAs($this->user)->get('/vendors/compliance');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('stats.total_vendors')
            ->has('stats.compliant')
            ->has('stats.expired')
            ->has('stats.expiring_soon')
            ->has('stats.do_not_use')
        );
    }

    // ==================== Compare Page Tests ====================

    public function test_authenticated_user_can_access_compare_page(): void
    {
        $this->skipIfNotPostgres();

        Vendor::factory()->withTrade('Plumbing')->create();

        $response = $this->actingAs($this->user)->get('/vendors/compare');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Vendors/Compare')
            ->has('vendors')
            ->has('comparison')
            ->has('trades')
            ->has('selectedTrade')
        );
    }

    public function test_compare_page_filters_by_trade(): void
    {
        $this->skipIfNotPostgres();

        Vendor::factory()->withTrade('Plumbing')->create(['company_name' => 'Plumber A']);
        Vendor::factory()->withTrade('Plumbing')->create(['company_name' => 'Plumber B']);
        Vendor::factory()->withTrade('Electrical')->create(['company_name' => 'Electrician']);

        $response = $this->actingAs($this->user)->get('/vendors/compare?trade=Plumbing');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('vendors', 2)
            ->where('selectedTrade', 'Plumbing')
        );
    }

    public function test_compare_page_calculates_comparison_metrics(): void
    {
        $this->skipIfNotPostgres();

        $vendor1 = Vendor::factory()->withTrade('Plumbing')->create();
        $vendor2 = Vendor::factory()->withTrade('Plumbing')->create();

        WorkOrder::factory()->count(5)
            ->forVendor($vendor1)
            ->withAmount(100.00)
            ->openedAt(now()->subMonths(6))
            ->create();

        WorkOrder::factory()->count(10)
            ->forVendor($vendor2)
            ->withAmount(200.00)
            ->openedAt(now()->subMonths(6))
            ->create();

        $response = $this->actingAs($this->user)->get('/vendors/compare?trade=Plumbing');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('comparison.work_order_count')
            ->has('comparison.total_spend')
        );
    }

    // ==================== Deduplication Page Tests ====================

    public function test_non_admin_cannot_access_deduplication_page(): void
    {
        $response = $this->actingAs($this->user)->get('/vendors/deduplication');

        $response->assertStatus(403);
    }

    public function test_admin_can_access_deduplication_page(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/vendors/deduplication');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Vendors/Deduplication')
            ->has('canonicalGroups')
            ->has('allCanonicalVendors')
            ->has('stats')
        );
    }

    public function test_deduplication_page_shows_canonical_groups(): void
    {
        $canonical = Vendor::factory()->create(['company_name' => 'Main Vendor']);
        Vendor::factory()->duplicateOf($canonical)->create(['company_name' => 'Duplicate 1']);
        Vendor::factory()->duplicateOf($canonical)->create(['company_name' => 'Duplicate 2']);

        $response = $this->actingAs($this->adminUser)->get('/vendors/deduplication');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('canonicalGroups', 1)
            ->where('canonicalGroups.0.company_name', 'Main Vendor')
            ->where('canonicalGroups.0.duplicate_count', 2)
        );
    }

    public function test_deduplication_page_stats(): void
    {
        $canonical1 = Vendor::factory()->create();
        $canonical2 = Vendor::factory()->create();
        Vendor::factory()->duplicateOf($canonical1)->create();
        Vendor::factory()->duplicateOf($canonical1)->create();

        $response = $this->actingAs($this->adminUser)->get('/vendors/deduplication');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('stats.total_vendors', 4)
            ->where('stats.canonical_vendors', 2)
            ->where('stats.duplicate_vendors', 2)
            ->where('stats.canonical_with_duplicates', 1)
        );
    }

    // ==================== Edge Cases ====================

    public function test_vendor_index_handles_empty_results(): void
    {
        $this->skipIfNotPostgres();

        $response = $this->actingAs($this->user)->get('/vendors?search=nonexistent');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('vendors.data', 0)
        );
    }

    public function test_vendor_index_preserves_filter_params(): void
    {
        $this->skipIfNotPostgres();

        Vendor::factory()->withTrade('Plumbing')->create();

        $response = $this->actingAs($this->user)->get('/vendors?search=test&trade=Plumbing&is_active=1');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('filters.search', 'test')
            ->where('filters.trade', 'Plumbing')
            ->where('filters.is_active', true) // Converted to boolean by form request
        );
    }

    public function test_compliance_page_excludes_inactive_vendors(): void
    {
        Vendor::factory()->inactive()->create([
            'company_name' => 'Inactive Vendor',
            'workers_comp_expires' => now()->subDays(10),
        ]);

        $response = $this->actingAs($this->user)->get('/vendors/compliance');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('expired', 0)
        );
    }

    public function test_compliance_page_excludes_do_not_use_from_categories(): void
    {
        Vendor::factory()->doNotUse()->create([
            'company_name' => 'Banned with Expired',
            'workers_comp_expires' => now()->subDays(10),
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->get('/vendors/compliance');

        $response->assertStatus(200);
        // Should be in doNotUse, not in expired
        $response->assertInertia(fn ($page) => $page
            ->has('doNotUse', 1)
            ->has('expired', 0)
        );
    }

    // ==================== Validation Tests ====================

    public function test_vendor_index_rejects_invalid_insurance_status(): void
    {
        $this->skipIfNotPostgres();

        $response = $this->actingAs($this->user)->get('/vendors?insurance_status=invalid_status');

        $response->assertStatus(302);
        $response->assertSessionHasErrors('insurance_status');
    }

    public function test_vendor_index_rejects_invalid_canonical_filter(): void
    {
        $this->skipIfNotPostgres();

        $response = $this->actingAs($this->user)->get('/vendors?canonical_filter=invalid_filter');

        $response->assertStatus(302);
        $response->assertSessionHasErrors('canonical_filter');
    }

    public function test_vendor_index_rejects_invalid_sort_field(): void
    {
        $this->skipIfNotPostgres();

        $response = $this->actingAs($this->user)->get('/vendors?sort=invalid_field');

        $response->assertStatus(302);
        $response->assertSessionHasErrors('sort');
    }

    public function test_vendor_index_rejects_invalid_sort_direction(): void
    {
        $this->skipIfNotPostgres();

        $response = $this->actingAs($this->user)->get('/vendors?direction=invalid');

        $response->assertStatus(302);
        $response->assertSessionHasErrors('direction');
    }

    public function test_vendor_index_rejects_too_long_search_query(): void
    {
        $this->skipIfNotPostgres();

        $longSearch = str_repeat('a', 256);
        $response = $this->actingAs($this->user)->get('/vendors?search='.$longSearch);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('search');
    }

    public function test_vendor_index_rejects_invalid_page_number(): void
    {
        $this->skipIfNotPostgres();

        $response = $this->actingAs($this->user)->get('/vendors?page=0');

        $response->assertStatus(302);
        $response->assertSessionHasErrors('page');
    }

    public function test_vendor_index_accepts_valid_insurance_statuses(): void
    {
        $this->skipIfNotPostgres();

        $validStatuses = ['expired', 'expiring_soon', 'current'];

        foreach ($validStatuses as $status) {
            $response = $this->actingAs($this->user)->get('/vendors?insurance_status='.$status);

            $response->assertStatus(200);
        }
    }

    public function test_vendor_index_accepts_valid_canonical_filters(): void
    {
        $this->skipIfNotPostgres();

        $validFilters = ['canonical_only', 'all', 'duplicates_only'];

        foreach ($validFilters as $filter) {
            $response = $this->actingAs($this->user)->get('/vendors?canonical_filter='.$filter);

            $response->assertStatus(200);
        }
    }

    public function test_vendor_index_accepts_valid_sort_fields(): void
    {
        $this->skipIfNotPostgres();

        $validSorts = ['company_name', 'vendor_type', 'is_active', 'work_orders_count'];

        foreach ($validSorts as $sort) {
            $response = $this->actingAs($this->user)->get('/vendors?sort='.$sort);

            $response->assertStatus(200);
        }
    }

    public function test_vendor_index_accepts_valid_sort_directions(): void
    {
        $this->skipIfNotPostgres();

        $validDirections = ['asc', 'desc'];

        foreach ($validDirections as $direction) {
            $response = $this->actingAs($this->user)->get('/vendors?direction='.$direction);

            $response->assertStatus(200);
        }
    }

    public function test_vendor_index_accepts_boolean_is_active_values(): void
    {
        $this->skipIfNotPostgres();

        $validValues = ['0', '1', 'true', 'false'];

        foreach ($validValues as $value) {
            $response = $this->actingAs($this->user)->get('/vendors?is_active='.$value);

            $response->assertStatus(200);
        }
    }
}
