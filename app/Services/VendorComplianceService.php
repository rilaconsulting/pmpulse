<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class VendorComplianceService
{
    /**
     * Insurance type field mappings.
     */
    private const INSURANCE_TYPES = [
        'workers_comp_expires' => 'Workers Comp',
        'liability_ins_expires' => 'Liability',
        'auto_ins_expires' => 'Auto',
    ];

    /**
     * Get insurance issues for a vendor, categorized by severity.
     *
     * @return array{expired: array, expiring_soon: array, expiring_quarter: array, missing: array}
     */
    public function getInsuranceIssues(
        Vendor $vendor,
        ?Carbon $today = null,
        ?Carbon $thirtyDays = null,
        ?Carbon $ninetyDays = null
    ): array {
        $today = $today ?? now()->startOfDay();
        $thirtyDays = $thirtyDays ?? $today->copy()->addDays(30);
        $ninetyDays = $ninetyDays ?? $today->copy()->addDays(90);

        $issues = [
            'expired' => [],
            'expiring_soon' => [],
            'expiring_quarter' => [],
            'missing' => [],
        ];

        foreach (self::INSURANCE_TYPES as $field => $label) {
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
                    'days_past' => $today->diffInDays($date, true),
                ];
            } elseif ($date <= $thirtyDays) {
                $issues['expiring_soon'][] = [
                    'type' => $label,
                    'field' => $field,
                    'date' => $date->toDateString(),
                    'days_until' => $today->diffInDays($date, true),
                ];
            } elseif ($date <= $ninetyDays) {
                $issues['expiring_quarter'][] = [
                    'type' => $label,
                    'field' => $field,
                    'date' => $date->toDateString(),
                    'days_until' => $today->diffInDays($date, true),
                ];
            }
        }

        return $issues;
    }

    /**
     * Get workers comp specific issues from a collection of vendors.
     *
     * @return array{expired: array, expiring_soon: array, missing: array, current: array}
     */
    public function getWorkersCompIssues(
        Collection $vendors,
        ?Carbon $today = null,
        ?Carbon $thirtyDays = null
    ): array {
        $today = $today ?? now()->startOfDay();
        $thirtyDays = $thirtyDays ?? $today->copy()->addDays(30);

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
                    'days_past' => $today->diffInDays($date, true),
                ];
            } elseif ($date <= $thirtyDays) {
                $issues['expiring_soon'][] = [
                    'vendor' => $vendor,
                    'date' => $date->toDateString(),
                    'days_until' => $today->diffInDays($date, true),
                ];
            } else {
                $issues['current'][] = [
                    'vendor' => $vendor,
                    'date' => $date->toDateString(),
                    'days_until' => $today->diffInDays($date, true),
                ];
            }
        }

        return $issues;
    }

    /**
     * Get insurance status for display.
     *
     * @return array{workers_comp: string, liability: string, auto: string, overall: string}
     */
    public function getInsuranceStatus(Vendor $vendor, ?Carbon $today = null): array
    {
        $today = $today ?? now()->startOfDay();
        $thirtyDays = $today->copy()->addDays(30);

        $statuses = [];

        // Workers Comp
        $statuses['workers_comp'] = $this->getFieldStatus($vendor->workers_comp_expires, $today, $thirtyDays);

        // Liability Insurance
        $statuses['liability'] = $this->getFieldStatus($vendor->liability_ins_expires, $today, $thirtyDays);

        // Auto Insurance
        $statuses['auto'] = $this->getFieldStatus($vendor->auto_ins_expires, $today, $thirtyDays);

        // Overall status
        $statuses['overall'] = $this->calculateOverallStatus($statuses);

        return $statuses;
    }

    /**
     * Get the status for a single insurance field.
     */
    private function getFieldStatus(?Carbon $date, Carbon $today, Carbon $thirtyDays): string
    {
        if (! $date) {
            return 'missing';
        }

        if ($date < $today) {
            return 'expired';
        }

        if ($date <= $thirtyDays) {
            return 'expiring_soon';
        }

        return 'current';
    }

    /**
     * Calculate the overall insurance status from individual statuses.
     */
    private function calculateOverallStatus(array $statuses): string
    {
        // Remove 'overall' if it exists to avoid self-reference
        $individualStatuses = array_filter($statuses, fn ($key) => $key !== 'overall', ARRAY_FILTER_USE_KEY);

        if (in_array('expired', $individualStatuses, true)) {
            return 'expired';
        }

        if (in_array('expiring_soon', $individualStatuses, true)) {
            return 'expiring_soon';
        }

        // Check if all are missing
        $nonMissingStatuses = array_filter($individualStatuses, fn ($s) => $s !== 'missing');
        if (empty($nonMissingStatuses)) {
            return 'missing';
        }

        return 'current';
    }

    /**
     * Categorize a collection of vendors by their insurance compliance status.
     *
     * @return array{expired: array, expiring_soon: array, expiring_quarter: array, missing_info: array, compliant: array}
     *
     * @deprecated Use fetchComplianceCategoriesFromDatabase() for better performance
     */
    public function categorizeVendorsByCompliance(Collection $vendors): array
    {
        $today = now()->startOfDay();
        $thirtyDays = $today->copy()->addDays(30);
        $ninetyDays = $today->copy()->addDays(90);

        $expired = [];
        $expiringSoon = [];
        $expiringQuarter = [];
        $missingInfo = [];
        $compliant = [];

        foreach ($vendors as $vendor) {
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

        return [
            'expired' => $expired,
            'expiring_soon' => $expiringSoon,
            'expiring_quarter' => $expiringQuarter,
            'missing_info' => $missingInfo,
            'compliant' => $compliant,
        ];
    }

    /**
     * Fetch compliance categories directly from database using optimized queries.
     * Much more efficient than loading all vendors into memory.
     *
     * @return array{expired: array, expiring_soon: array, expiring_quarter: array, missing_info: array, compliant: array}
     */
    public function fetchComplianceCategoriesFromDatabase(): array
    {
        $today = now()->startOfDay();
        $thirtyDays = $today->copy()->addDays(30);
        $ninetyDays = $today->copy()->addDays(90);

        // Base query for canonical, active, usable vendors
        $baseQuery = fn () => Vendor::query()
            ->canonical()
            ->active()
            ->usable()
            ->orderBy('company_name');

        // Fetch expired vendors
        $expiredVendors = $baseQuery()
            ->withExpiredInsurance()
            ->get();

        $expired = $expiredVendors->map(function ($vendor) use ($today) {
            return [
                'vendor' => $vendor,
                'issues' => $this->getExpiredIssues($vendor, $today),
            ];
        })->all();

        // Fetch expiring soon vendors (within 30 days, not already expired)
        $expiringSoonVendors = $baseQuery()
            ->withExpiringSoonInsurance(30)
            ->whereNotIn('id', $expiredVendors->pluck('id'))
            ->get();

        $expiringSoon = $expiringSoonVendors->map(function ($vendor) use ($today, $thirtyDays) {
            return [
                'vendor' => $vendor,
                'issues' => $this->getExpiringSoonIssues($vendor, $today, $thirtyDays),
            ];
        })->all();

        // Fetch expiring quarter vendors (31-90 days)
        $expiringQuarterVendors = $baseQuery()
            ->withExpiringQuarterInsurance()
            ->get();

        $expiringQuarter = $expiringQuarterVendors->map(function ($vendor) use ($thirtyDays, $ninetyDays) {
            return [
                'vendor' => $vendor,
                'issues' => $this->getExpiringQuarterIssues($vendor, $thirtyDays, $ninetyDays),
            ];
        })->all();

        // Fetch vendors with missing info
        $missingInfoVendors = $baseQuery()
            ->withMissingInsurance()
            ->get();

        $missingInfo = $missingInfoVendors->map(function ($vendor) {
            return [
                'vendor' => $vendor,
                'issues' => $this->getMissingIssues($vendor),
            ];
        })->all();

        // Fetch fully compliant vendors
        $compliant = $baseQuery()
            ->fullyCompliant()
            ->get()
            ->all();

        return [
            'expired' => $expired,
            'expiring_soon' => $expiringSoon,
            'expiring_quarter' => $expiringQuarter,
            'missing_info' => $missingInfo,
            'compliant' => $compliant,
        ];
    }

    /**
     * Get expired insurance issues for a vendor.
     */
    private function getExpiredIssues(Vendor $vendor, Carbon $today): array
    {
        $issues = [];

        foreach (self::INSURANCE_TYPES as $field => $label) {
            $date = $vendor->$field;

            if ($date && $date < $today) {
                $issues[] = [
                    'type' => $label,
                    'field' => $field,
                    'date' => $date->toDateString(),
                    'days_past' => $today->diffInDays($date, true),
                ];
            }
        }

        return $issues;
    }

    /**
     * Get expiring soon insurance issues for a vendor (within 30 days).
     */
    private function getExpiringSoonIssues(Vendor $vendor, Carbon $today, Carbon $thirtyDays): array
    {
        $issues = [];

        foreach (self::INSURANCE_TYPES as $field => $label) {
            $date = $vendor->$field;

            if ($date && $date >= $today && $date <= $thirtyDays) {
                $issues[] = [
                    'type' => $label,
                    'field' => $field,
                    'date' => $date->toDateString(),
                    'days_until' => $today->diffInDays($date, true),
                ];
            }
        }

        return $issues;
    }

    /**
     * Get expiring quarter insurance issues for a vendor (31-90 days).
     */
    private function getExpiringQuarterIssues(Vendor $vendor, Carbon $thirtyDays, Carbon $ninetyDays): array
    {
        $issues = [];
        $today = now()->startOfDay();

        foreach (self::INSURANCE_TYPES as $field => $label) {
            $date = $vendor->$field;

            if ($date && $date > $thirtyDays && $date <= $ninetyDays) {
                $issues[] = [
                    'type' => $label,
                    'field' => $field,
                    'date' => $date->toDateString(),
                    'days_until' => $today->diffInDays($date, true),
                ];
            }
        }

        return $issues;
    }

    /**
     * Get missing insurance info for a vendor.
     */
    private function getMissingIssues(Vendor $vendor): array
    {
        $issues = [];

        foreach (self::INSURANCE_TYPES as $field => $label) {
            if (! $vendor->$field) {
                $issues[] = [
                    'type' => $label,
                    'field' => $field,
                ];
            }
        }

        return $issues;
    }

    /**
     * Get workers comp issues directly from database.
     *
     * @return array{expired: array, expiring_soon: array, missing: array, current: array}
     */
    public function fetchWorkersCompIssuesFromDatabase(): array
    {
        $today = now()->startOfDay();
        $thirtyDays = $today->copy()->addDays(30);

        $baseQuery = fn () => Vendor::query()
            ->canonical()
            ->active()
            ->usable()
            ->orderBy('company_name');

        // Expired workers comp
        $expired = $baseQuery()
            ->whereNotNull('workers_comp_expires')
            ->where('workers_comp_expires', '<', $today)
            ->get()
            ->map(fn ($vendor) => [
                'vendor' => $vendor,
                'date' => $vendor->workers_comp_expires->toDateString(),
                'days_past' => $today->diffInDays($vendor->workers_comp_expires, true),
            ])
            ->all();

        // Expiring soon (within 30 days)
        $expiringSoon = $baseQuery()
            ->whereNotNull('workers_comp_expires')
            ->whereBetween('workers_comp_expires', [$today, $thirtyDays])
            ->get()
            ->map(fn ($vendor) => [
                'vendor' => $vendor,
                'date' => $vendor->workers_comp_expires->toDateString(),
                'days_until' => $today->diffInDays($vendor->workers_comp_expires, true),
            ])
            ->all();

        // Missing workers comp
        $missing = $baseQuery()
            ->whereNull('workers_comp_expires')
            ->get()
            ->all();

        // Current (more than 30 days out)
        $current = $baseQuery()
            ->whereNotNull('workers_comp_expires')
            ->where('workers_comp_expires', '>', $thirtyDays)
            ->get()
            ->map(fn ($vendor) => [
                'vendor' => $vendor,
                'date' => $vendor->workers_comp_expires->toDateString(),
                'days_until' => $today->diffInDays($vendor->workers_comp_expires, true),
            ])
            ->all();

        return [
            'expired' => $expired,
            'expiring_soon' => $expiringSoon,
            'missing' => $missing,
            'current' => $current,
        ];
    }

    /**
     * Get compliance summary statistics using optimized count queries.
     *
     * @return array{total: int, compliant: int, expired: int, expiring_soon: int, expiring_quarter: int, missing_info: int}
     */
    public function getComplianceStats(): array
    {
        $baseQuery = fn () => Vendor::query()
            ->canonical()
            ->active()
            ->usable();

        return [
            'total' => $baseQuery()->count(),
            'expired' => $baseQuery()->withExpiredInsurance()->count(),
            'expiring_soon' => $baseQuery()->withExpiringSoonInsurance(30)->count(),
            'expiring_quarter' => $baseQuery()->withExpiringQuarterInsurance()->count(),
            'missing_info' => $baseQuery()->withMissingInsurance()->count(),
            'compliant' => $baseQuery()->fullyCompliant()->count(),
        ];
    }
}
