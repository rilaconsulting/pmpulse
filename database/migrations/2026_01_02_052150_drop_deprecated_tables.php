<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Drop deprecated tables that have been replaced by the unified settings table.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('sync_configurations');
        Schema::dropIfExists('feature_flags');
    }

    /**
     * Reverse the migrations.
     *
     * Note: These tables are intentionally not recreated as their functionality
     * has been migrated to the settings table. A fresh install should use the
     * settings table instead.
     */
    public function down(): void
    {
        // Tables are not recreated - use settings table instead
    }
};
