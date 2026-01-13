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
            $table->foreignUuid('bill_detail_id')
                ->nullable()
                ->after('external_expense_id')
                ->constrained('bill_details')
                ->nullOnDelete();

            $table->index('bill_detail_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('utility_expenses', function (Blueprint $table) {
            $table->dropForeign(['bill_detail_id']);
            $table->dropIndex(['bill_detail_id']);
            $table->dropColumn('bill_detail_id');
        });
    }
};
