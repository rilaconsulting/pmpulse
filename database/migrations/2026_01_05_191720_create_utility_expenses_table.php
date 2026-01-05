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
        Schema::create('utility_expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('property_id')->constrained()->cascadeOnDelete();
            $table->string('utility_type', 20); // water, electric, gas, garbage, sewer, other
            $table->date('expense_date'); // bill date from AppFolio
            $table->date('period_start')->nullable(); // billing period start
            $table->date('period_end')->nullable(); // billing period end
            $table->decimal('amount', 12, 2);
            $table->string('vendor_name')->nullable();
            $table->string('description')->nullable();
            $table->string('external_expense_id'); // reference to raw expense
            $table->timestamps();

            // Unique constraint on external expense ID to prevent duplicates
            $table->unique('external_expense_id');

            // Indexes for efficient querying
            $table->index(['property_id', 'utility_type']);
            $table->index(['property_id', 'expense_date']);
            $table->index(['utility_type', 'expense_date']);
            $table->index('expense_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('utility_expenses');
    }
};
