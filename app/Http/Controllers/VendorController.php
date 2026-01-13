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
        $stats = [
            'total_vendors' => Vendor::canonical()->count(),
            'active_vendors' => Vendor::canonical()->active()->count(),
            'expired_insurance' => Vendor::canonical()->active()->get()->filter(fn ($v) => $v->hasExpiredInsurance())->count(),
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
     * Display vendor compliance report.
     */
    public function compliance(Request $request): Response
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
