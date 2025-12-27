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
        Schema::create('daily_kpis', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->decimal('occupancy_rate', 5, 2)->default(0); // Percentage
            $table->unsignedInteger('vacancy_count')->default(0);
            $table->unsignedInteger('total_units')->default(0);
            $table->decimal('delinquency_amount', 12, 2)->default(0);
            $table->unsignedInteger('delinquent_units')->default(0);
            $table->unsignedInteger('open_work_orders')->default(0);
            $table->decimal('avg_days_open_work_orders', 8, 2)->default(0);
            $table->unsignedInteger('work_orders_opened')->default(0); // Opened that day
            $table->unsignedInteger('work_orders_closed')->default(0); // Closed that day
            $table->decimal('total_rent_collected', 12, 2)->default(0);
            $table->decimal('total_rent_due', 12, 2)->default(0);
            $table->timestamps();

            // Index for date range queries
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_kpis');
    }
};
