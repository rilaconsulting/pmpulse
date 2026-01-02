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
        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('external_id')->unique(); // AppFolio transaction ID
            $table->foreignUuid('property_id')
                ->nullable()
                ->constrained('properties')
                ->nullOnDelete();
            $table->foreignUuid('unit_id')
                ->nullable()
                ->constrained('units')
                ->nullOnDelete();
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
