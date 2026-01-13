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
        Schema::table('work_orders', function (Blueprint $table) {
            // Vendor relationship - UUID FK referencing vendors table
            $table->foreignUuid('vendor_id')
                ->nullable()
                ->after('unit_id')
                ->constrained('vendors')
                ->nullOnDelete();

            // Denormalized vendor name for display
            $table->string('vendor_name')->nullable()->after('vendor_id');

            // Cost fields
            $table->decimal('amount', 12, 2)->nullable()->after('vendor_name');
            $table->decimal('vendor_bill_amount', 12, 2)->nullable()->after('amount');
            $table->decimal('estimate_amount', 12, 2)->nullable()->after('vendor_bill_amount');

            // Additional work order metadata
            $table->string('vendor_trade')->nullable()->after('estimate_amount');
            $table->string('work_order_type')->nullable()->after('vendor_trade');

            // Index for vendor lookup
            $table->index('vendor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->dropColumn([
                'vendor_id',
                'vendor_name',
                'amount',
                'vendor_bill_amount',
                'estimate_amount',
                'vendor_trade',
                'work_order_type',
            ]);
        });
    }
};
