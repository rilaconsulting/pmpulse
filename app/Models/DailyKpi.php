<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyKpi extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'daily_kpis';

    protected $fillable = [
        'date',
        'occupancy_rate',
        'vacancy_count',
        'total_units',
        'delinquency_amount',
        'delinquent_units',
        'open_work_orders',
        'avg_days_open_work_orders',
        'work_orders_opened',
        'work_orders_closed',
        'total_rent_collected',
        'total_rent_due',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'occupancy_rate' => 'decimal:2',
            'delinquency_amount' => 'decimal:2',
            'avg_days_open_work_orders' => 'decimal:2',
            'total_rent_collected' => 'decimal:2',
            'total_rent_due' => 'decimal:2',
        ];
    }

    /**
     * Scope to get KPIs for a date range.
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * Scope to get the latest KPI entry.
     */
    public function scopeLatest($query)
    {
        return $query->orderByDesc('date');
    }
}
