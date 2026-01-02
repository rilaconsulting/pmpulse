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
        Schema::create('settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('category'); // e.g., 'sync', 'business_hours', 'alerts', 'features', 'appfolio'
            $table->string('key'); // Setting name within category
            $table->json('value')->nullable(); // Flexible value storage
            $table->boolean('encrypted')->default(false); // Flag for encrypted values
            $table->string('description')->nullable(); // Human-readable description
            $table->timestamps();

            // Unique constraint on category + key combination
            $table->unique(['category', 'key']);

            // Index for faster category lookups
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
