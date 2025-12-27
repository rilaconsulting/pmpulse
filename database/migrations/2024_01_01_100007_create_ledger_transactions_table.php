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
        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->unique(); // AppFolio transaction ID
            $table->foreignId('property_id')
                ->nullable()
                ->constrained('properties')
                ->onDelete('set null');
            $table->foreignId('unit_id')
                ->nullable()
                ->constrained('units')
                ->onDelete('set null');
            $table->date('date');
            $table->string('type'); // charge, payment, credit, etc.
            $table->decimal('amount', 12, 2);
            $table->string('category')->nullable(); // rent, late_fee, utilities, etc.
            $table->string('description')->nullable();
            $table->decimal('balance', 12, 2)->nullable(); // Running balance if available
            $table->timestamps();

            // Indexes for reporting
            $table->index('date');
            $table->index('type');
            $table->index('category');
            $table->index(['property_id', 'date']);
            $table->index(['unit_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_transactions');
    }
};
