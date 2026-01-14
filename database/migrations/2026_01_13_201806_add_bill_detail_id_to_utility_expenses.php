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
        Schema::table('utility_expenses', function (Blueprint $table) {
            // Add foreign key to bill_details table for reference tracking
            // Note: foreignUuid with constrained() automatically creates an index
            $table->foreignUuid('bill_detail_id')
                ->nullable()
                ->after('external_expense_id')
                ->constrained('bill_details')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('utility_expenses', function (Blueprint $table) {
            // Drop foreign key constraint (also drops the associated index)
            $table->dropForeign(['bill_detail_id']);
            $table->dropColumn('bill_detail_id');
        });
    }
};
