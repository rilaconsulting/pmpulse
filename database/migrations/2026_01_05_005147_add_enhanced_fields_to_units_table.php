<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds enhanced unit fields from AppFolio API.
     */
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            // Unit classification
            $table->string('unit_type')->nullable()->after('unit_number');

            // Additional rent information
            $table->decimal('advertised_rent', 10, 2)->nullable()->after('market_rent');

            // Rentability flag (for excluding from vacancy calculations)
            $table->boolean('rentable')->default(true)->after('is_active');

            // Index for filtering
            $table->index('unit_type');
            $table->index('rentable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropIndex(['unit_type']);
            $table->dropIndex(['rentable']);

            $table->dropColumn([
                'unit_type',
                'advertised_rent',
                'rentable',
            ]);
        });
    }
};
