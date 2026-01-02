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
     *
     * IMPORTANT: This migration permanently drops the legacy configuration tables.
     *
     * Before running this migration in production, ensure that any required data
     * from the `sync_configurations` and `feature_flags` tables has been migrated
     * to the unified `settings` table via the SettingsSeeder or manual migration.
     *
     * The SettingsSeeder will populate default values. Any custom configuration
     * in the old tables should be manually transferred before running this migration.
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
