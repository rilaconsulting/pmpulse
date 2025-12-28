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
        Schema::create('property_rollups', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('property_id')
                ->constrained('properties')
                ->onDelete('cascade');
            $table->unsignedInteger('vacancy_count')->default(0);
            $table->unsignedInteger('total_units')->default(0);
            $table->decimal('occupancy_rate', 5, 2)->default(0);
            $table->decimal('delinquency_amount', 12, 2)->default(0);
            $table->unsignedInteger('delinquent_units')->default(0);
            $table->unsignedInteger('open_work_orders')->default(0);
            $table->decimal('avg_days_open_work_orders', 8, 2)->default(0);
            $table->timestamps();

            // Unique constraint per property per day
            $table->unique(['date', 'property_id']);

            // Indexes for reporting
            $table->index('date');
            $table->index(['property_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_rollups');
    }
};
