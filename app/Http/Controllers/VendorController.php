<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\VendorIndexRequest;
use App\Models\Vendor;
use App\Services\VendorAnalyticsService;
use App\Services\VendorComplianceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VendorController extends Controller
{
    public function __construct(
        private readonly VendorAnalyticsService $analyticsService,
        private readonly VendorComplianceService $complianceService
    ) {}

    /**
     * Display a listing of vendors.
     */
    public function index(VendorIndexRequest $request): Response
    {
        // Handle canonical filter (validated by form request)
        $canonicalFilter = $request->validated('canonical_filter', 'canonical_only');

        $query = Vendor::query()
            ->with([
                'duplicateVendors:id,canonical_vendor_id,company_name,contact_name,phone,email', // For canonical vendors
                'canonicalVendor:id,company_name', // For duplicate vendors when showing all
            ])
            ->withCount(['workOrders', 'duplicateVendors']);

        // Apply canonical filtering
        $query = match ($canonicalFilter) {
            'canonical_only' => $query->canonical(),
            'all' => $query, // No filter
            'duplicates_only' => $query->duplicates(),
            default => $query->canonical(),
        };

        // Search by name, contact, or email
        if ($search = $request->validated('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'ILIKE', "%{$search}%")
                    ->orWhere('contact_name', 'ILIKE', "%{$search}%")
                    ->orWhere('email', 'ILIKE', "%{$search}%");
            });
        }

        // Filter by trade
        if ($trade = $request->validated('trade')) {
            $query->where('vendor_trades', 'ILIKE', "%{$trade}%");
        }

        // Filter by active status
        if ($request->validated('is_active') !== null) {
            $query->where('is_active', $request->validated('is_active'));
        }

        // Filter by insurance status
        if ($insuranceStatus = $request->validated('insurance_status')) {
            $query->withInsuranceStatus($insuranceStatus);
        }

        // Sorting (validated by form request)
        $sortField = $request->validated('sort', 'company_name');
        $sortDirection = $request->validated('direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        $vendors = $query->paginate(15)->withQueryString();

        // Get unique trades for filter
        $allTrades = $this->analyticsService->getAllTrades();

        // Get unique vendor types for filter
        $vendorTypes = Vendor::canonical()
            ->whereNotNull('vendor_type')
            ->distinct()
            ->pluck('vendor_type')
            ->sort()
            ->values();

        // Calculate metrics for each vendor (last 12 months)
        $period = ['type' => 'last_12_months', 'date' => now()];
        $vendors->getCollection()->transform(function ($vendor) use ($period) {
            $vendor->metrics = [
                'work_order_count' => $this->analyticsService->getWorkOrderCount($vendor, $period),
                'total_spend' => $this->analyticsService->getTotalSpend($vendor, $period),
                'avg_cost_per_wo' => $this->analyticsService->getAverageCostPerWO($vendor, $period),
            ];

            // Insurance status for display
            $vendor->insurance_status = $this->complianceService->getInsuranceStatus($vendor);

            return $vendor;
        });

        // Get summary stats
        $stats = [
            'total_vendors' => Vendor::canonical()->count(),
            'active_vendors' => Vendor::canonical()->active()->count(),
            'expired_insurance' => Vendor::canonical()->active()->withExpiredInsurance()->count(),
            'portfolio_stats' => $this->analyticsService->getPortfolioStats($period),
        ];

        return Inertia::render('Vendors/Index', [
            'vendors' => $vendors,
            'trades' => $allTrades,
            'vendorTypes' => $vendorTypes,
            'stats' => $stats,
            'filters' => [
                'search' => $request->validated('search', ''),
                'trade' => $request->validated('trade', ''),
                'is_active' => $request->input('is_active', ''), // Keep original for display
                'insurance_status' => $request->validated('insurance_status', ''),
                'canonical_filter' => $canonicalFilter,
                'sort' => $sortField,
                'direction' => $sortDirection,
            ],
        ]);
    }

    /**
     * Display a single vendor's details.
     */
    public function show(Request $request, Vendor $vendor): Response
    {
        // Load relationships
        $vendor->load(['duplicateVendors', 'canonicalVendor']);

        // Get the canonical vendor for grouping
        $canonicalVendor = $vendor->getCanonicalVendor();

        // Period for metrics (last 12 months)
        $period = ['type' => 'last_12_months', 'date' => now()];

        // Get metrics summary
        $metrics = $this->analyticsService->getVendorSummary($canonicalVendor, $period);

        // Get period comparison
        $periodComparison = $this->analyticsService->getPeriodComparison($canonicalVendor);

        // Get trade analysis
        $tradeAnalysis = $this->analyticsService->getVendorTradeAnalysis($canonicalVendor, $period);

        // Get response time metrics
        $responseMetrics = $this->analyticsService->getResponseTimeMetrics($canonicalVendor, $period);

        // Get response time comparison to portfolio
        $responseComparison = $this->analyticsService->compareResponseTimeToPortfolio($canonicalVendor, $period);

        // Get spending trend (last 12 months)
        $spendTrend = $this->analyticsService->getVendorTrend($canonicalVendor, 12, 'month');

        // Get spend by property
        $spendByProperty = $this->analyticsService->getSpendByProperty($canonicalVendor);

        // Get insurance status
        $insuranceStatus = $this->complianceService->getInsuranceStatus($vendor);

        // Get work orders with filtering and sorting
        $sortField = $request->get('wo_sort', 'opened_at');
        $sortDirection = $request->get('wo_direction', 'desc');

        $workOrders = $this->analyticsService->getVendorWorkOrders($canonicalVendor, [
            'status' => $request->get('wo_status'),
            'property_id' => $request->get('wo_property'),
            'sort' => $sortField,
            'direction' => $sortDirection,
        ]);

        // Get unique properties for filter dropdown
        $workOrderProperties = $this->analyticsService->getVendorWorkOrderProperties($canonicalVendor);

        // Get work order statistics
        $workOrderStats = $this->analyticsService->getVendorWorkOrderStats($canonicalVendor);

        return Inertia::render('Vendors/Show', [
            'vendor' => $vendor,
            'metrics' => $metrics,
            'periodComparison' => $periodComparison,
            'tradeAnalysis' => $tradeAnalysis,
            'responseMetrics' => $responseMetrics,
            'responseComparison' => $responseComparison,
            'spendTrend' => $spendTrend,
            'spendByProperty' => $spendByProperty,
            'insuranceStatus' => $insuranceStatus,
            'workOrders' => $workOrders,
            'workOrderProperties' => $workOrderProperties,
            'workOrderStats' => $workOrderStats,
            'workOrderFilters' => [
                'wo_status' => $request->get('wo_status', ''),
                'wo_property' => $request->get('wo_property', ''),
                'wo_sort' => $sortField,
                'wo_direction' => $sortDirection,
            ],
        ]);
    }

    /**
     * Display vendor compliance report.
     */
    public function compliance(): Response
    {
        // Get all active canonical vendors
        $allVendors = Vendor::query()
            ->canonical()
            ->active()
            ->usable()
            ->orderBy('company_name')
            ->get();

        // Categorize vendors by insurance status using the compliance service
        $categories = $this->complianceService->categorizeVendorsByCompliance($allVendors);

        // Get vendors marked as "do not use"
        $doNotUse = Vendor::query()
            ->canonical()
            ->where('do_not_use', true)
            ->orderBy('company_name')
            ->get();

        // Workers comp specific tracking
        $workersCompIssues = $this->complianceService->getWorkersCompIssues($allVendors);

        // Summary stats
        $stats = [
            'total_vendors' => $allVendors->count(),
            'compliant' => count($categories['compliant']),
            'expired' => count($categories['expired']),
            'expiring_soon' => count($categories['expiring_soon']),
            'expiring_quarter' => count($categories['expiring_quarter']),
            'missing_info' => count($categories['missing_info']),
            'do_not_use' => $doNotUse->count(),
            'workers_comp_issues' => count($workersCompIssues['expired']) + count($workersCompIssues['expiring_soon']) + count($workersCompIssues['missing']),
        ];

        return Inertia::render('Vendors/Compliance', [
            'expired' => $categories['expired'],
            'expiringSoon' => $categories['expiring_soon'],
            'expiringQuarter' => $categories['expiring_quarter'],
            'missingInfo' => $categories['missing_info'],
            'compliant' => $categories['compliant'],
            'doNotUse' => $doNotUse,
            'workersCompIssues' => $workersCompIssues,
            'stats' => $stats,
        ]);
    }

    /**
     * Display vendor comparison view.
     */
    public function compare(Request $request): Response
    {
        // Get all unique trades
        $allTrades = $this->analyticsService->getAllTrades();

        // Selected trade - validate it exists in the list to prevent SQL injection
        $requestedTrade = $request->get('trade', $allTrades[0] ?? null);
        $selectedTrade = in_array($requestedTrade, $allTrades, true) ? $requestedTrade : ($allTrades[0] ?? null);

        // Get vendors in the selected trade with metrics
        $vendors = [];
        $comparison = [];

        if ($selectedTrade) {
            $period = ['type' => 'last_12_months', 'date' => now()];
            $vendors = $this->analyticsService->getVendorsForComparison(
                $selectedTrade,
                $period,
                $this->complianceService
            );
            $comparison = $this->analyticsService->calculateComparisonStats($vendors);
        }

        return Inertia::render('Vendors/Compare', [
            'vendors' => $vendors,
            'comparison' => $comparison,
            'trades' => $allTrades,
            'selectedTrade' => $selectedTrade,
        ]);
    }

    /**
     * Display vendor deduplication management page.
     */
    public function deduplication(): Response
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        // Get canonical vendors with their duplicates
        $canonicalGroups = Vendor::query()
            ->canonical()
            ->has('duplicateVendors')
            ->with(['duplicateVendors' => fn ($q) => $q->orderBy('company_name')])
            ->withCount('duplicateVendors')
            ->orderBy('company_name')
            ->get()
            ->map(fn ($vendor) => [
                'id' => $vendor->id,
                'company_name' => $vendor->company_name,
                'contact_name' => $vendor->contact_name,
                'email' => $vendor->email,
                'phone' => $vendor->phone,
                'vendor_trades' => $vendor->vendor_trades,
                'duplicate_count' => $vendor->duplicate_vendors_count,
                'duplicates' => $vendor->duplicateVendors->map(fn ($dup) => [
                    'id' => $dup->id,
                    'company_name' => $dup->company_name,
                    'contact_name' => $dup->contact_name,
                    'email' => $dup->email,
                    'phone' => $dup->phone,
                ]),
            ]);

        // Get all canonical vendors for linking dropdown
        $allCanonicalVendors = Vendor::query()
            ->canonical()
            ->orderBy('company_name')
            ->get(['id', 'company_name', 'contact_name', 'vendor_trades']);

        // Stats
        $stats = [
            'total_vendors' => Vendor::count(),
            'canonical_vendors' => Vendor::canonical()->count(),
            'duplicate_vendors' => Vendor::duplicates()->count(),
            'canonical_with_duplicates' => $canonicalGroups->count(),
        ];

        return Inertia::render('Vendors/Deduplication', [
            'canonicalGroups' => $canonicalGroups,
            'allCanonicalVendors' => $allCanonicalVendors,
            'stats' => $stats,
        ]);
    }

}
