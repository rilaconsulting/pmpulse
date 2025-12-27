<?php

namespace App\Services;

use App\Models\DailyKpi;
use App\Models\LedgerTransaction;
use App\Models\Property;
use App\Models\PropertyRollup;
use App\Models\Unit;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Analytics Service
 *
 * This service calculates and refreshes analytics data.
 * It computes KPIs at both portfolio and property levels.
 */
class AnalyticsService
{
    /**
     * Refresh all analytics for a given date.
     */
    public function refreshForDate(Carbon $date): void
    {
        Log::info('Refreshing analytics', ['date' => $date->toDateString()]);

        DB::transaction(function () use ($date) {
            $this->refreshDailyKpis($date);
            $this->refreshPropertyRollups($date);
        });

        Log::info('Analytics refresh completed', ['date' => $date->toDateString()]);
    }

    /**
     * Refresh analytics for today.
     */
    public function refreshToday(): void
    {
        $this->refreshForDate(now());
    }

    /**
     * Refresh daily KPIs (portfolio level).
     */
    private function refreshDailyKpis(Carbon $date): void
    {
        $totalUnits = Unit::active()->count();
        $vacantUnits = Unit::active()->vacant()->count();
        $occupiedUnits = $totalUnits - $vacantUnits;

        $occupancyRate = $totalUnits > 0
            ? ($occupiedUnits / $totalUnits) * 100
            : 0;

        // Calculate delinquency (outstanding charges)
        $delinquency = $this->calculateDelinquency($date);

        // Calculate work order metrics
        $workOrderMetrics = $this->calculateWorkOrderMetrics($date);

        // Calculate rent collected/due for the month
        $rentMetrics = $this->calculateRentMetrics($date);

        DailyKpi::updateOrCreate(
            ['date' => $date->toDateString()],
            [
                'occupancy_rate' => round($occupancyRate, 2),
                'vacancy_count' => $vacantUnits,
                'total_units' => $totalUnits,
                'delinquency_amount' => $delinquency['amount'],
                'delinquent_units' => $delinquency['units'],
                'open_work_orders' => $workOrderMetrics['open_count'],
                'avg_days_open_work_orders' => $workOrderMetrics['avg_days_open'],
                'work_orders_opened' => $workOrderMetrics['opened_today'],
                'work_orders_closed' => $workOrderMetrics['closed_today'],
                'total_rent_collected' => $rentMetrics['collected'],
                'total_rent_due' => $rentMetrics['due'],
            ]
        );
    }

    /**
     * Refresh property-level rollups.
     */
    private function refreshPropertyRollups(Carbon $date): void
    {
        $properties = Property::active()->with('units')->get();

        foreach ($properties as $property) {
            $totalUnits = $property->units()->active()->count();
            $vacantUnits = $property->units()->active()->vacant()->count();
            $occupiedUnits = $totalUnits - $vacantUnits;

            $occupancyRate = $totalUnits > 0
                ? ($occupiedUnits / $totalUnits) * 100
                : 0;

            // Property-level delinquency
            $delinquency = $this->calculatePropertyDelinquency($property, $date);

            // Property-level work orders
            $workOrderMetrics = $this->calculatePropertyWorkOrderMetrics($property, $date);

            PropertyRollup::updateOrCreate(
                [
                    'date' => $date->toDateString(),
                    'property_id' => $property->id,
                ],
                [
                    'vacancy_count' => $vacantUnits,
                    'total_units' => $totalUnits,
                    'occupancy_rate' => round($occupancyRate, 2),
                    'delinquency_amount' => $delinquency['amount'],
                    'delinquent_units' => $delinquency['units'],
                    'open_work_orders' => $workOrderMetrics['open_count'],
                    'avg_days_open_work_orders' => $workOrderMetrics['avg_days_open'],
                ]
            );
        }
    }

    /**
     * Calculate portfolio-level delinquency.
     */
    private function calculateDelinquency(Carbon $date): array
    {
        // Sum of outstanding charges (charges minus payments for occupied units)
        // This is a simplified calculation - may need adjustment based on actual data structure
        $charges = LedgerTransaction::charges()
            ->where('date', '<=', $date)
            ->sum('amount');

        $payments = LedgerTransaction::payments()
            ->where('date', '<=', $date)
            ->sum('amount');

        $delinquencyAmount = max(0, $charges - $payments);

        // Count units with outstanding balance
        // This is a simplified approach - in production, you'd want a more accurate calculation
        $delinquentUnits = LedgerTransaction::query()
            ->whereNotNull('unit_id')
            ->where('date', '<=', $date)
            ->select('unit_id')
            ->selectRaw('SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as charges', ['charge'])
            ->selectRaw('SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as payments', ['payment'])
            ->groupBy('unit_id')
            ->havingRaw('SUM(CASE WHEN type = ? THEN amount ELSE 0 END) > SUM(CASE WHEN type = ? THEN amount ELSE 0 END)', ['charge', 'payment'])
            ->count();

        return [
            'amount' => $delinquencyAmount,
            'units' => $delinquentUnits,
        ];
    }

    /**
     * Calculate property-level delinquency.
     */
    private function calculatePropertyDelinquency(Property $property, Carbon $date): array
    {
        $charges = LedgerTransaction::charges()
            ->where('property_id', $property->id)
            ->where('date', '<=', $date)
            ->sum('amount');

        $payments = LedgerTransaction::payments()
            ->where('property_id', $property->id)
            ->where('date', '<=', $date)
            ->sum('amount');

        $delinquencyAmount = max(0, $charges - $payments);

        $delinquentUnits = LedgerTransaction::query()
            ->where('property_id', $property->id)
            ->whereNotNull('unit_id')
            ->where('date', '<=', $date)
            ->select('unit_id')
            ->selectRaw('SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as charges', ['charge'])
            ->selectRaw('SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as payments', ['payment'])
            ->groupBy('unit_id')
            ->havingRaw('SUM(CASE WHEN type = ? THEN amount ELSE 0 END) > SUM(CASE WHEN type = ? THEN amount ELSE 0 END)', ['charge', 'payment'])
            ->count();

        return [
            'amount' => $delinquencyAmount,
            'units' => $delinquentUnits,
        ];
    }

    /**
     * Calculate portfolio-level work order metrics.
     */
    private function calculateWorkOrderMetrics(Carbon $date): array
    {
        $openWorkOrders = WorkOrder::open()->get();
        $openCount = $openWorkOrders->count();

        // Calculate average days open
        $avgDaysOpen = $openCount > 0
            ? $openWorkOrders->avg(fn ($wo) => $wo->days_open)
            : 0;

        // Work orders opened today
        $openedToday = WorkOrder::whereDate('opened_at', $date)->count();

        // Work orders closed today
        $closedToday = WorkOrder::whereDate('closed_at', $date)->count();

        return [
            'open_count' => $openCount,
            'avg_days_open' => round($avgDaysOpen, 2),
            'opened_today' => $openedToday,
            'closed_today' => $closedToday,
        ];
    }

    /**
     * Calculate property-level work order metrics.
     */
    private function calculatePropertyWorkOrderMetrics(Property $property, Carbon $date): array
    {
        $openWorkOrders = WorkOrder::where('property_id', $property->id)->open()->get();
        $openCount = $openWorkOrders->count();

        $avgDaysOpen = $openCount > 0
            ? $openWorkOrders->avg(fn ($wo) => $wo->days_open)
            : 0;

        return [
            'open_count' => $openCount,
            'avg_days_open' => round($avgDaysOpen, 2),
        ];
    }

    /**
     * Calculate rent collected and due for the month.
     */
    private function calculateRentMetrics(Carbon $date): array
    {
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();

        // Rent collected this month
        $collected = LedgerTransaction::payments()
            ->whereBetween('date', [$startOfMonth, $date])
            ->where('category', 'rent')
            ->sum('amount');

        // Rent due this month (from active leases)
        $due = \App\Models\Lease::query()
            ->where('status', 'active')
            ->where('start_date', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $date);
            })
            ->sum('rent');

        return [
            'collected' => $collected,
            'due' => $due,
        ];
    }

    /**
     * Get property rollups for the dashboard.
     */
    public function getPropertyRollups(?Carbon $date = null): array
    {
        $date = $date ?? now();

        return PropertyRollup::with('property')
            ->where('date', $date->toDateString())
            ->get()
            ->map(function ($rollup) {
                return [
                    'property_id' => $rollup->property_id,
                    'property_name' => $rollup->property->name,
                    'vacancy_count' => $rollup->vacancy_count,
                    'total_units' => $rollup->total_units,
                    'occupancy_rate' => $rollup->occupancy_rate,
                    'delinquency_amount' => $rollup->delinquency_amount,
                    'open_work_orders' => $rollup->open_work_orders,
                ];
            })
            ->toArray();
    }

    /**
     * Get KPI trends for a date range.
     */
    public function getKpiTrend(Carbon $startDate, Carbon $endDate): array
    {
        return DailyKpi::whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    /**
     * Get the latest KPIs.
     */
    public function getLatestKpis(): ?DailyKpi
    {
        return DailyKpi::latest('date')->first();
    }
}
