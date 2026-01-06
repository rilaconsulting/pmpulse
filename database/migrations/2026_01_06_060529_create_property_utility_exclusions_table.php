<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates a table for utility-specific property exclusions.
     * This allows excluding a property from specific utility types
     * (e.g., tenant pays electric but landlord pays water).
     */
    public function up(): void
    {
        Schema::create('property_utility_exclusions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('property_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('utility_type', 50);
            $table->text('reason')->nullable();

            $table->foreignUuid('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // Unique constraint: one exclusion per property per utility type
            $table->unique(['property_id', 'utility_type']);

            // Index for querying exclusions by utility type
            $table->index('utility_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_utility_exclusions');
    }
};
