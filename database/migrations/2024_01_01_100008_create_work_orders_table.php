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
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->unique(); // AppFolio work order ID
            $table->foreignId('property_id')
                ->nullable()
                ->constrained('properties')
                ->onDelete('set null');
            $table->foreignId('unit_id')
                ->nullable()
                ->constrained('units')
                ->onDelete('set null');
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->string('status')->default('open'); // open, in_progress, completed, cancelled
            $table->string('priority')->default('normal'); // low, normal, high, emergency
            $table->string('category')->nullable(); // plumbing, electrical, hvac, etc.
            $table->text('description')->nullable();
            $table->timestamps();

            // Indexes for reporting and filtering
            $table->index('status');
            $table->index('priority');
            $table->index('category');
            $table->index('opened_at');
            $table->index('closed_at');
            $table->index(['property_id', 'status']);
            $table->index(['status', 'opened_at']); // For aging reports
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
