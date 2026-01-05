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
        Schema::create('property_adjustments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('property_id')->constrained()->cascadeOnDelete();
            $table->string('field_name', 50);
            $table->string('original_value')->nullable();
            $table->string('adjusted_value');
            $table->date('effective_from');
            $table->date('effective_to')->nullable(); // null = permanent
            $table->text('reason');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Indexes for efficient querying
            $table->index(['property_id', 'field_name']);
            $table->index(['property_id', 'effective_from', 'effective_to']);
            $table->index('field_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_adjustments');
    }
};
