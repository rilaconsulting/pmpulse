<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bill_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sync_run_id')
                ->nullable()
                ->constrained('sync_runs')
                ->nullOnDelete();

            // AppFolio unique identifiers
            $table->unsignedBigInteger('txn_id')->unique(); // Transaction ID - primary external ID
            $table->unsignedBigInteger('payable_invoice_detail_id')->nullable(); // Bill Detail ID

            // Bill information
            $table->string('reference_number')->nullable();
            $table->date('bill_date')->nullable();
            $table->date('due_date')->nullable();
            $table->text('description')->nullable();

            // GL Account
            $table->string('gl_account')->nullable(); // Full "6210 - Water" format
            $table->string('gl_account_name')->nullable();
            $table->string('gl_account_number')->nullable(); // Just the number
            $table->unsignedBigInteger('gl_account_id')->nullable();

            // Property (store external ID, link to properties table)
            $table->string('property_external_id')->nullable();
            $table->foreignUuid('property_id')
                ->nullable()
                ->constrained('properties')
                ->nullOnDelete();

            // Unit (store external ID, link to units table)
            $table->string('unit_external_id')->nullable();
            $table->foreignUuid('unit_id')
                ->nullable()
                ->constrained('units')
                ->nullOnDelete();

            // Payee/Vendor
            $table->string('payee_name')->nullable();
            $table->unsignedBigInteger('party_id')->nullable(); // Payee ID
            $table->string('party_type')->nullable(); // Payee Type
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->string('vendor_account_number')->nullable();

            // Amounts
            $table->decimal('paid', 12, 2)->nullable();
            $table->decimal('unpaid', 12, 2)->nullable();
            $table->decimal('quantity', 12, 4)->nullable();
            $table->decimal('rate', 12, 4)->nullable();

            // Payment information
            $table->string('check_number')->nullable();
            $table->date('payment_date')->nullable();
            $table->string('cash_account')->nullable();
            $table->string('bank_account')->nullable();
            $table->string('other_payment_type')->nullable();

            // Work order linkage
            $table->string('work_order_number')->nullable();
            $table->unsignedBigInteger('work_order_id')->nullable();
            $table->string('work_order_assignee')->nullable();
            $table->string('work_order_issue')->nullable();
            $table->unsignedBigInteger('service_request_id')->nullable();

            // Purchase order
            $table->string('purchase_order_number')->nullable();
            $table->unsignedBigInteger('purchase_order_id')->nullable();

            // Service period
            $table->date('service_from')->nullable();
            $table->date('service_to')->nullable();

            // Approval workflow
            $table->string('approval_status')->nullable();
            $table->string('approved_by')->nullable();
            $table->string('last_approver')->nullable();
            $table->text('next_approvers')->nullable();
            $table->string('days_pending_approval')->nullable();
            $table->string('board_approval_status')->nullable();

            // Cost center
            $table->string('cost_center_name')->nullable();
            $table->string('cost_center_number')->nullable();

            // Audit fields from AppFolio
            $table->string('created_by')->nullable();
            $table->timestamp('txn_created_at')->nullable();
            $table->timestamp('txn_updated_at')->nullable();

            // Sync tracking
            $table->timestamp('pulled_at');
            $table->timestamps();

            // Indexes for common queries
            // Note: sync_run_id, property_id, unit_id already indexed by foreignUuid constraints
            $table->index('property_external_id');
            $table->index('vendor_id');
            $table->index('work_order_id');
            $table->index('gl_account_number');
            $table->index('bill_date');
            $table->index('payment_date');
            $table->index('pulled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bill_details');
    }
};
