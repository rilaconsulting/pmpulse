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
}
