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
     * Adds enhanced property fields for geocoding and additional AppFolio data.
     */
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // Geocoding coordinates
            $table->decimal('latitude', 10, 7)->nullable()->after('zip');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');

            // Portfolio information
            $table->string('portfolio')->nullable()->after('longitude');
            $table->unsignedInteger('portfolio_id')->nullable()->after('portfolio');

            // Additional property details
            $table->unsignedSmallInteger('year_built')->nullable()->after('property_type');
            $table->unsignedInteger('total_sqft')->nullable()->after('year_built');
            $table->string('county')->nullable()->after('total_sqft');

            // Index for geocoding queries
            $table->index(['latitude', 'longitude'], 'properties_coordinates_index');
            $table->index('portfolio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex('properties_coordinates_index');
            $table->dropIndex(['portfolio']);

            $table->dropColumn([
                'latitude',
                'longitude',
                'portfolio',
                'portfolio_id',
                'year_built',
                'total_sqft',
                'county',
            ]);
        });
    }
};
