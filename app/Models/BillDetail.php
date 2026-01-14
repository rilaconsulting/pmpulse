<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillDetail extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'sync_run_id',
        'txn_id',
        'payable_invoice_detail_id',
        'reference_number',
        'bill_date',
        'due_date',
        'description',
        'gl_account',
        'gl_account_name',
        'gl_account_number',
        'gl_account_id',
        'property_external_id',
        'property_id',
        'unit_external_id',
        'unit_id',
        'payee_name',
        'party_id',
        'party_type',
        'vendor_id',
        'vendor_account_number',
        'paid',
        'unpaid',
        'quantity',
        'rate',
        'check_number',
        'payment_date',
        'cash_account',
        'bank_account',
        'other_payment_type',
        'work_order_number',
        'work_order_id',
        'work_order_assignee',
        'work_order_issue',
        'service_request_id',
        'purchase_order_number',
        'purchase_order_id',
        'service_from',
        'service_to',
        'approval_status',
        'approved_by',
        'last_approver',
        'next_approvers',
        'days_pending_approval',
        'board_approval_status',
        'cost_center_name',
        'cost_center_number',
        'created_by',
        'txn_created_at',
        'txn_updated_at',
        'pulled_at',
    ];

    protected function casts(): array
    {
        return [
            'txn_id' => 'integer',
            'payable_invoice_detail_id' => 'integer',
            'bill_date' => 'date',
            'due_date' => 'date',
            'gl_account_id' => 'integer',
            'party_id' => 'integer',
            'vendor_id' => 'integer',
            'paid' => 'decimal:2',
            'unpaid' => 'decimal:2',
            'quantity' => 'decimal:4',
            'rate' => 'decimal:4',
            'payment_date' => 'date',
            'work_order_id' => 'integer',
            'service_request_id' => 'integer',
            'purchase_order_id' => 'integer',
            'service_from' => 'date',
            'service_to' => 'date',
            'txn_created_at' => 'datetime',
            'txn_updated_at' => 'datetime',
            'pulled_at' => 'datetime',
        ];
    }

    /**
     * Get the sync run this bill detail belongs to.
     */
    public function syncRun(): BelongsTo
    {
        return $this->belongsTo(SyncRun::class);
    }

    /**
     * Get the property this bill detail belongs to.
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Get the unit this bill detail belongs to.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Scope to filter by property.
     */
    public function scopeForProperty(Builder $query, string $propertyId): Builder
    {
        return $query->where('property_id', $propertyId);
    }

    /**
     * Scope to filter by GL account number.
     */
    public function scopeForGlAccount(Builder $query, string $accountNumber): Builder
    {
        return $query->where('gl_account_number', $accountNumber);
    }

    /**
     * Scope to filter by work order.
     */
    public function scopeForWorkOrder(Builder $query, int $workOrderId): Builder
    {
        return $query->where('work_order_id', $workOrderId);
    }

    /**
     * Scope to filter by vendor.
     */
    public function scopeForVendor(Builder $query, int $vendorId): Builder
    {
        return $query->where('vendor_id', $vendorId);
    }

    /**
     * Scope for utility expenses (matching GL accounts in utility_accounts).
     * Uses a subquery for better performance instead of fetching IDs first.
     */
    public function scopeUtilityExpenses(Builder $query): Builder
    {
        return $query->whereIn('gl_account_number', function ($subQuery) {
            $subQuery->select('gl_account_number')
                ->from('utility_accounts')
                ->where('is_active', true);
        });
    }

    /**
     * Get the total amount (paid + unpaid).
     */
    public function getTotalAmountAttribute(): float
    {
        return ($this->paid ?? 0) + ($this->unpaid ?? 0);
    }

    /**
     * Check if this bill is fully paid.
     */
    public function isPaid(): bool
    {
        return ($this->unpaid ?? 0) == 0 && ($this->paid ?? 0) > 0;
    }

    /**
     * Check if this bill has a linked work order.
     */
    public function hasWorkOrder(): bool
    {
        return $this->work_order_id !== null;
    }
}
