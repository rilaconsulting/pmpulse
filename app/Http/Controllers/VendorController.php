<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Services\VendorAnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VendorController extends Controller
{
    public function __construct(
        private readonly VendorAnalyticsService $analyticsService
    ) {}

    /**
     * Display a listing of vendors.
     */
    public function index(Request $request): Response
    {
        $query = Vendor::query()
            ->canonical()
            ->with(['duplicateVendors']) // Eager load for getAllGroupVendorIds()
            ->withCount(['workOrders', 'duplicateVendors']);

        // Search by name, contact, or email
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'ILIKE', "%{$search}%")
                    ->orWhere('contact_name', 'ILIKE', "%{$search}%")
                    ->orWhere('email', 'ILIKE', "%{$search}%");
            });
        }

        // Filter by trade
        if ($trade = $request->get('trade')) {
            $query->where('vendor_trades', 'ILIKE', "%{$trade}%");
        }

        // Filter by active status
        if ($request->has('is_active') && $request->get('is_active') !== '') {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by insurance status
        if ($insuranceStatus = $request->get('insurance_status')) {
            $today = now()->startOfDay();
            if ($insuranceStatus === 'expired') {
                $query->where(function ($q) use ($today) {
                    $q->where('workers_comp_expires', '<', $today)
                        ->orWhere('liability_ins_expires', '<', $today)
                        ->orWhere('auto_ins_expires', '<', $today);
                });
            } elseif ($insuranceStatus === 'expiring_soon') {
                $thirtyDays = $today->copy()->addDays(30);
                $query->where(function ($q) use ($today, $thirtyDays) {
                    $q->whereBetween('workers_comp_expires', [$today, $thirtyDays])
                        ->orWhereBetween('liability_ins_expires', [$today, $thirtyDays])
                        ->orWhereBetween('auto_ins_expires', [$today, $thirtyDays]);
                });
            } elseif ($insuranceStatus === 'current') {
                $query->where(function ($q) use ($today) {
                    $q->where(function ($sub) use ($today) {
                        $sub->whereNull('workers_comp_expires')
                            ->orWhere('workers_comp_expires', '>=', $today);
                    })->where(function ($sub) use ($today) {
                        $sub->whereNull('liability_ins_expires')
                            ->orWhere('liability_ins_expires', '>=', $today);
                    })->where(function ($sub) use ($today) {
                        $sub->whereNull('auto_ins_expires')
                            ->orWhere('auto_ins_expires', '>=', $today);
                    });
                });
            }
        }

        // Sorting
        $sortField = $request->get('sort', 'company_name');
        $sortDirection = $request->get('direction', 'asc');
        $allowedSorts = ['company_name', 'vendor_type', 'is_active', 'work_orders_count'];

        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection === 'desc' ? 'desc' : 'asc');
        }

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
            $vendor->insurance_status = $this->getInsuranceStatus($vendor);

            return $vendor;
        });

        // Get summary stats
        $today = now()->startOfDay();
        $stats = [
            'total_vendors' => Vendor::canonical()->count(),
            'active_vendors' => Vendor::canonical()->active()->count(),
            'expired_insurance' => Vendor::canonical()->active()
                ->where(function ($q) use ($today) {
                    $q->where('workers_comp_expires', '<', $today)
                        ->orWhere('liability_ins_expires', '<', $today)
                        ->orWhere('auto_ins_expires', '<', $today);
                })->count(),
            'portfolio_stats' => $this->analyticsService->getPortfolioStats($period),
        ];

        return Inertia::render('Vendors/Index', [
            'vendors' => $vendors,
            'trades' => $allTrades,
            'vendorTypes' => $vendorTypes,
            'stats' => $stats,
            'filters' => [
                'search' => $request->get('search', ''),
                'trade' => $request->get('trade', ''),
                'is_active' => $request->get('is_active', ''),
                'insurance_status' => $request->get('insurance_status', ''),
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

        // Get previous year trend for comparison
        $previousYearTrend = $this->analyticsService->getVendorTrend(
            $canonicalVendor,
            12,
            'month'
        );

        // Get spend by property
        $spendByProperty = $this->getSpendByProperty($canonicalVendor, $period);

        // Get insurance status
        $insuranceStatus = $this->getInsuranceStatus($vendor);

        // Build work orders query with filtering and sorting
        $vendorIds = $canonicalVendor->getAllGroupVendorIds();
        $workOrdersQuery = \App\Models\WorkOrder::query()
            ->whereIn('vendor_id', $vendorIds)
            ->with('property:id,name');

        // Filter by status
        if ($status = $request->get('wo_status')) {
            $workOrdersQuery->where('status', $status);
        }

        // Filter by property
        if ($propertyId = $request->get('wo_property')) {
            $workOrdersQuery->where('property_id', $propertyId);
        }

        // Sorting
        $sortField = $request->get('wo_sort', 'opened_at');
        $sortDirection = $request->get('wo_direction', 'desc');
        $allowedSorts = ['opened_at', 'closed_at', 'amount', 'status'];

        if (in_array($sortField, $allowedSorts)) {
            $workOrdersQuery->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $workOrdersQuery->orderBy('opened_at', 'desc');
        }

        $workOrders = $workOrdersQuery->paginate(10, ['*'], 'wo_page')->withQueryString();

        // Get unique properties for filter dropdown
        $workOrderProperties = \App\Models\WorkOrder::query()
            ->whereIn('vendor_id', $vendorIds)
            ->whereNotNull('property_id')
            ->with('property:id,name')
            ->distinct('property_id')
            ->get()
            ->pluck('property')
            ->filter()
            ->unique('id')
            ->values();

        // Get work order status counts
        $workOrderStats = [
            'total' => \App\Models\WorkOrder::whereIn('vendor_id', $vendorIds)->count(),
            'completed' => \App\Models\WorkOrder::whereIn('vendor_id', $vendorIds)->where('status', 'completed')->count(),
            'open' => \App\Models\WorkOrder::whereIn('vendor_id', $vendorIds)->where('status', 'open')->count(),
            'in_progress' => \App\Models\WorkOrder::whereIn('vendor_id', $vendorIds)->where('status', 'in_progress')->count(),
            'total_spend' => \App\Models\WorkOrder::whereIn('vendor_id', $vendorIds)->sum('amount'),
        ];

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
        $today = now()->startOfDay();
        $thirtyDays = $today->copy()->addDays(30);
        $ninetyDays = $today->copy()->addDays(90);

        // Get all active canonical vendors
        $allVendors = Vendor::query()
            ->canonical()
            ->active()
            ->usable()
            ->orderBy('company_name')
            ->get();

        // Categorize vendors by insurance status
        $expired = [];
        $expiringSoon = []; // Next 30 days
        $expiringQuarter = []; // 31-90 days
        $missingInfo = [];
        $compliant = [];

        foreach ($allVendors as $vendor) {
            $issues = $this->getInsuranceIssues($vendor, $today, $thirtyDays, $ninetyDays);

            if (! empty($issues['expired'])) {
                $expired[] = [
                    'vendor' => $vendor,
                    'issues' => $issues['expired'],
                ];
            } elseif (! empty($issues['expiring_soon'])) {
                $expiringSoon[] = [
                    'vendor' => $vendor,
                    'issues' => $issues['expiring_soon'],
                ];
            } elseif (! empty($issues['expiring_quarter'])) {
                $expiringQuarter[] = [
                    'vendor' => $vendor,
                    'issues' => $issues['expiring_quarter'],
                ];
            } elseif (! empty($issues['missing'])) {
                $missingInfo[] = [
                    'vendor' => $vendor,
                    'issues' => $issues['missing'],
                ];
            } else {
                $compliant[] = $vendor;
            }
        }

        // Get vendors marked as "do not use"
        $doNotUse = Vendor::query()
            ->canonical()
            ->where('do_not_use', true)
            ->orderBy('company_name')
            ->get();

        // Workers comp specific tracking
        $workersCompIssues = $this->getWorkersCompIssues($allVendors, $today, $thirtyDays);

        // Summary stats
        $stats = [
            'total_vendors' => $allVendors->count(),
            'compliant' => count($compliant),
            'expired' => count($expired),
            'expiring_soon' => count($expiringSoon),
            'expiring_quarter' => count($expiringQuarter),
            'missing_info' => count($missingInfo),
            'do_not_use' => $doNotUse->count(),
            'workers_comp_issues' => count($workersCompIssues['expired']) + count($workersCompIssues['expiring_soon']) + count($workersCompIssues['missing']),
        ];

        return Inertia::render('Vendors/Compliance', [
            'expired' => $expired,
            'expiringSoon' => $expiringSoon,
            'expiringQuarter' => $expiringQuarter,
            'missingInfo' => $missingInfo,
            'compliant' => $compliant,
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

        // Selected trade
        $selectedTrade = $request->get('trade', $allTrades[0] ?? null);

        // Get vendors in the selected trade
        $vendors = [];
        $comparison = [];

        if ($selectedTrade) {
            $vendorQuery = Vendor::query()
                ->canonical()
                ->active()
                ->where('vendor_trades', 'ILIKE', "%{$selectedTrade}%")
                ->orderBy('company_name');

            $vendorsInTrade = $vendorQuery->get();

            $period = ['type' => 'last_12_months', 'date' => now()];

            // Calculate metrics for each vendor
            foreach ($vendorsInTrade as $vendor) {
                $metrics = $this->analyticsService->getVendorSummary($vendor, $period);
                $insuranceStatus = $this->getInsuranceStatus($vendor);

                $vendors[] = [
                    'id' => $vendor->id,
                    'company_name' => $vendor->company_name,
                    'contact_name' => $vendor->contact_name,
                    'phone' => $vendor->phone,
                    'email' => $vendor->email,
                    'is_active' => $vendor->is_active,
                    'work_order_count' => $metrics['work_order_count'] ?? 0,
                    'total_spend' => $metrics['total_spend'] ?? 0,
                    'avg_cost_per_wo' => $metrics['avg_cost_per_wo'] ?? null,
                    'avg_completion_time' => $metrics['avg_completion_time'] ?? null,
                    'insurance_status' => $insuranceStatus,
                ];
            }

            // Find best and worst values
            if (count($vendors) > 1) {
                $metrics = ['work_order_count', 'total_spend', 'avg_cost_per_wo', 'avg_completion_time'];

                foreach ($metrics as $metric) {
                    $values = array_filter(array_column($vendors, $metric), fn ($v) => $v !== null);

                    if (empty($values)) {
                        continue;
                    }

                    // For most metrics, higher is better for work orders, lower is better for cost/time
                    $best = ($metric === 'work_order_count' || $metric === 'total_spend')
                        ? max($values)
                        : min($values);
                    $worst = ($metric === 'work_order_count' || $metric === 'total_spend')
                        ? min($values)
                        : max($values);

                    $comparison[$metric] = [
                        'best' => $best,
                        'worst' => $worst,
                        'avg' => count($values) > 0 ? array_sum($values) / count($values) : null,
                    ];
                }
            }
        }

        return Inertia::render('Vendors/Compare', [
            'vendors' => $vendors,
            'comparison' => $comparison,
            'trades' => $allTrades,
            'selectedTrade' => $selectedTrade,
        ]);
    }

    /**
     * Get spend breakdown by property for a vendor.
     */
    private function getSpendByProperty(Vendor $vendor, array $period): array
    {
        $vendorIds = $vendor->getAllGroupVendorIds();

        // Get work orders grouped by property
        $workOrders = \App\Models\WorkOrder::query()
            ->whereIn('vendor_id', $vendorIds)
            ->whereNotNull('property_id')
            ->whereNotNull('amount')
            ->where('amount', '>', 0)
            ->with('property:id,name')
            ->get();

        // Group by property and sum amounts
        $byProperty = $workOrders->groupBy('property_id')->map(function ($wos, $propertyId) {
            $property = $wos->first()?->property;

            return [
                'property_id' => $propertyId,
                'property_name' => $property?->name ?? 'Unknown',
                'total_spend' => $wos->sum('amount'),
                'work_order_count' => $wos->count(),
            ];
        })->values()->sortByDesc('total_spend')->take(10)->values()->toArray();

        return $byProperty;
    }

    /**
     * Get insurance issues for a vendor.
     */
    private function getInsuranceIssues(Vendor $vendor, $today, $thirtyDays, $ninetyDays): array
    {
        $issues = [
            'expired' => [],
            'expiring_soon' => [],
            'expiring_quarter' => [],
            'missing' => [],
        ];

        $insuranceTypes = [
            'workers_comp_expires' => 'Workers Comp',
            'liability_ins_expires' => 'Liability',
            'auto_ins_expires' => 'Auto',
        ];

        foreach ($insuranceTypes as $field => $label) {
            $date = $vendor->$field;

            if (! $date) {
                $issues['missing'][] = [
                    'type' => $label,
                    'field' => $field,
                ];
            } elseif ($date < $today) {
                $issues['expired'][] = [
                    'type' => $label,
                    'field' => $field,
                    'date' => $date->toDateString(),
                    'days_past' => $today->diffInDays($date),
                ];
            } elseif ($date <= $thirtyDays) {
                $issues['expiring_soon'][] = [
                    'type' => $label,
                    'field' => $field,
                    'date' => $date->toDateString(),
                    'days_until' => $today->diffInDays($date),
                ];
            } elseif ($date <= $ninetyDays) {
                $issues['expiring_quarter'][] = [
                    'type' => $label,
                    'field' => $field,
                    'date' => $date->toDateString(),
                    'days_until' => $today->diffInDays($date),
                ];
            }
        }

        return $issues;
    }

    /**
     * Get workers comp specific issues.
     */
    private function getWorkersCompIssues($vendors, $today, $thirtyDays): array
    {
        $issues = [
            'expired' => [],
            'expiring_soon' => [],
            'missing' => [],
            'current' => [],
        ];

        foreach ($vendors as $vendor) {
            $date = $vendor->workers_comp_expires;

            if (! $date) {
                $issues['missing'][] = $vendor;
            } elseif ($date < $today) {
                $issues['expired'][] = [
                    'vendor' => $vendor,
                    'date' => $date->toDateString(),
                    'days_past' => $today->diffInDays($date),
                ];
            } elseif ($date <= $thirtyDays) {
                $issues['expiring_soon'][] = [
                    'vendor' => $vendor,
                    'date' => $date->toDateString(),
                    'days_until' => $today->diffInDays($date),
                ];
            } else {
                $issues['current'][] = [
                    'vendor' => $vendor,
                    'date' => $date->toDateString(),
                    'days_until' => $today->diffInDays($date),
                ];
            }
        }

        return $issues;
    }

    /**
     * Get insurance status for display.
     */
    private function getInsuranceStatus(Vendor $vendor): array
    {
        $today = now()->startOfDay();
        $thirtyDays = $today->copy()->addDays(30);

        $statuses = [];

        // Workers Comp
        if ($vendor->workers_comp_expires) {
            if ($vendor->workers_comp_expires < $today) {
                $statuses['workers_comp'] = 'expired';
            } elseif ($vendor->workers_comp_expires <= $thirtyDays) {
                $statuses['workers_comp'] = 'expiring_soon';
            } else {
                $statuses['workers_comp'] = 'current';
            }
        } else {
            $statuses['workers_comp'] = 'missing';
        }

        // Liability Insurance
        if ($vendor->liability_ins_expires) {
            if ($vendor->liability_ins_expires < $today) {
                $statuses['liability'] = 'expired';
            } elseif ($vendor->liability_ins_expires <= $thirtyDays) {
                $statuses['liability'] = 'expiring_soon';
            } else {
                $statuses['liability'] = 'current';
            }
        } else {
            $statuses['liability'] = 'missing';
        }

        // Auto Insurance
        if ($vendor->auto_ins_expires) {
            if ($vendor->auto_ins_expires < $today) {
                $statuses['auto'] = 'expired';
            } elseif ($vendor->auto_ins_expires <= $thirtyDays) {
                $statuses['auto'] = 'expiring_soon';
            } else {
                $statuses['auto'] = 'current';
            }
        } else {
            $statuses['auto'] = 'missing';
        }

        // Overall status
        if (in_array('expired', $statuses)) {
            $statuses['overall'] = 'expired';
        } elseif (in_array('expiring_soon', $statuses)) {
            $statuses['overall'] = 'expiring_soon';
        } elseif (in_array('missing', $statuses) && count(array_filter($statuses, fn ($s) => $s !== 'missing')) === 0) {
            $statuses['overall'] = 'missing';
        } else {
            $statuses['overall'] = 'current';
        }

        return $statuses;
    }
}
