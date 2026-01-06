<?php

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
            // Drop indexes that include utility_type
            $table->dropIndex(['property_id', 'utility_type']);
            $table->dropIndex(['utility_type', 'expense_date']);

            // Drop the column
            $table->dropColumn('utility_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('utility_expenses', function (Blueprint $table) {
            $table->string('utility_type', 50)->nullable()->after('gl_account_number');
            $table->index(['property_id', 'utility_type']);
            $table->index(['utility_type', 'expense_date']);
        });
    }
};
