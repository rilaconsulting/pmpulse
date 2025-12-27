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
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->unique(); // AppFolio unit ID
            $table->foreignId('property_id')
                ->constrained('properties')
                ->onDelete('cascade');
            $table->string('unit_number');
            $table->unsignedInteger('sqft')->nullable();
            $table->unsignedTinyInteger('bedrooms')->nullable();
            $table->decimal('bathrooms', 3, 1)->nullable();
            $table->string('status')->default('vacant'); // vacant, occupied, not_ready, etc.
            $table->decimal('market_rent', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->index('status');
            $table->index(['property_id', 'status']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
