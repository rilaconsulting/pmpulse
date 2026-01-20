<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add utility_type_id column to all tables (nullable initially)
        $this->addUtilityTypeIdColumns();

        // 2. Populate utility_type_id from existing utility_type values
        $this->migrateData();

        // 3. Drop old indexes and constraints
        $this->dropOldIndexesAndConstraints();

        // 4. Make utility_type_id NOT NULL and add FK constraints
        $this->finalizeColumns();

        // 5. Create new indexes
        $this->createNewIndexes();

        // 6. Drop old utility_type columns
        $this->dropOldColumns();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Add back utility_type string columns
        $this->addOldColumns();

        // 2. Populate utility_type from utility_type_id
        $this->reverseMigrateData();

        // 3. Drop new indexes
        $this->dropNewIndexes();

        // 4. Drop FK constraints and utility_type_id columns
        $this->dropUtilityTypeIdColumns();

        // 5. Restore old indexes and constraints
        $this->restoreOldIndexesAndConstraints();
    }

    private function addUtilityTypeIdColumns(): void
    {
        Schema::table('utility_accounts', function (Blueprint $table) {
            $table->uuid('utility_type_id')->nullable()->after('utility_type');
        });

        Schema::table('utility_notes', function (Blueprint $table) {
            $table->uuid('utility_type_id')->nullable()->after('utility_type');
        });

        Schema::table('utility_formatting_rules', function (Blueprint $table) {
            $table->uuid('utility_type_id')->nullable()->after('utility_type');
        });

        Schema::table('property_utility_exclusions', function (Blueprint $table) {
            $table->uuid('utility_type_id')->nullable()->after('utility_type');
        });
    }

    private function migrateData(): void
    {
        // Update utility_accounts
        DB::statement('
            UPDATE utility_accounts
            SET utility_type_id = ut.id
            FROM utility_types ut
            WHERE utility_accounts.utility_type = ut.key
        ');

        // Update utility_notes
        DB::statement('
            UPDATE utility_notes
            SET utility_type_id = ut.id
            FROM utility_types ut
            WHERE utility_notes.utility_type = ut.key
        ');

        // Update utility_formatting_rules
        DB::statement('
            UPDATE utility_formatting_rules
            SET utility_type_id = ut.id
            FROM utility_types ut
            WHERE utility_formatting_rules.utility_type = ut.key
        ');

        // Update property_utility_exclusions
        DB::statement('
            UPDATE property_utility_exclusions
            SET utility_type_id = ut.id
            FROM utility_types ut
            WHERE property_utility_exclusions.utility_type = ut.key
        ');
    }

    private function dropOldIndexesAndConstraints(): void
    {
        Schema::table('utility_accounts', function (Blueprint $table) {
            $table->dropIndex('utility_accounts_utility_type_index');
        });

        Schema::table('utility_notes', function (Blueprint $table) {
            $table->dropUnique('utility_notes_property_id_utility_type_unique');
            $table->dropIndex('utility_notes_utility_type_index');
        });

        Schema::table('utility_formatting_rules', function (Blueprint $table) {
            $table->dropIndex('utility_formatting_rules_utility_type_enabled_index');
        });

        Schema::table('property_utility_exclusions', function (Blueprint $table) {
            $table->dropUnique('property_utility_exclusions_property_id_utility_type_unique');
            $table->dropIndex('property_utility_exclusions_utility_type_index');
        });
    }

    private function finalizeColumns(): void
    {
        // For any rows with NULL utility_type_id, set to 'other' type as fallback
        $otherTypeId = DB::table('utility_types')->where('key', 'other')->value('id');

        if ($otherTypeId) {
            DB::table('utility_accounts')->whereNull('utility_type_id')->update(['utility_type_id' => $otherTypeId]);
            DB::table('utility_notes')->whereNull('utility_type_id')->update(['utility_type_id' => $otherTypeId]);
            DB::table('utility_formatting_rules')->whereNull('utility_type_id')->update(['utility_type_id' => $otherTypeId]);
            DB::table('property_utility_exclusions')->whereNull('utility_type_id')->update(['utility_type_id' => $otherTypeId]);
        }

        Schema::table('utility_accounts', function (Blueprint $table) {
            $table->uuid('utility_type_id')->nullable(false)->change();
            $table->foreign('utility_type_id')->references('id')->on('utility_types')->restrictOnDelete();
        });

        Schema::table('utility_notes', function (Blueprint $table) {
            $table->uuid('utility_type_id')->nullable(false)->change();
            $table->foreign('utility_type_id')->references('id')->on('utility_types')->restrictOnDelete();
        });

        Schema::table('utility_formatting_rules', function (Blueprint $table) {
            $table->uuid('utility_type_id')->nullable(false)->change();
            $table->foreign('utility_type_id')->references('id')->on('utility_types')->restrictOnDelete();
        });

        Schema::table('property_utility_exclusions', function (Blueprint $table) {
            $table->uuid('utility_type_id')->nullable(false)->change();
            $table->foreign('utility_type_id')->references('id')->on('utility_types')->restrictOnDelete();
        });
    }

    private function createNewIndexes(): void
    {
        Schema::table('utility_accounts', function (Blueprint $table) {
            $table->index('utility_type_id');
        });

        Schema::table('utility_notes', function (Blueprint $table) {
            $table->unique(['property_id', 'utility_type_id']);
            $table->index('utility_type_id');
        });

        Schema::table('utility_formatting_rules', function (Blueprint $table) {
            $table->index(['utility_type_id', 'enabled']);
        });

        Schema::table('property_utility_exclusions', function (Blueprint $table) {
            $table->unique(['property_id', 'utility_type_id']);
            $table->index('utility_type_id');
        });
    }

    private function dropOldColumns(): void
    {
        Schema::table('utility_accounts', function (Blueprint $table) {
            $table->dropColumn('utility_type');
        });

        Schema::table('utility_notes', function (Blueprint $table) {
            $table->dropColumn('utility_type');
        });

        Schema::table('utility_formatting_rules', function (Blueprint $table) {
            $table->dropColumn('utility_type');
        });

        Schema::table('property_utility_exclusions', function (Blueprint $table) {
            $table->dropColumn('utility_type');
        });
    }

    // Rollback methods

    private function addOldColumns(): void
    {
        Schema::table('utility_accounts', function (Blueprint $table) {
            $table->string('utility_type', 20)->nullable()->after('gl_account_name');
        });

        Schema::table('utility_notes', function (Blueprint $table) {
            $table->string('utility_type', 20)->nullable()->after('property_id');
        });

        Schema::table('utility_formatting_rules', function (Blueprint $table) {
            $table->string('utility_type', 20)->nullable()->after('id');
        });

        Schema::table('property_utility_exclusions', function (Blueprint $table) {
            $table->string('utility_type', 50)->nullable()->after('property_id');
        });
    }

    private function reverseMigrateData(): void
    {
        DB::statement('
            UPDATE utility_accounts
            SET utility_type = ut.key
            FROM utility_types ut
            WHERE utility_accounts.utility_type_id = ut.id
        ');

        DB::statement('
            UPDATE utility_notes
            SET utility_type = ut.key
            FROM utility_types ut
            WHERE utility_notes.utility_type_id = ut.id
        ');

        DB::statement('
            UPDATE utility_formatting_rules
            SET utility_type = ut.key
            FROM utility_types ut
            WHERE utility_formatting_rules.utility_type_id = ut.id
        ');

        DB::statement('
            UPDATE property_utility_exclusions
            SET utility_type = ut.key
            FROM utility_types ut
            WHERE property_utility_exclusions.utility_type_id = ut.id
        ');

        // Make columns NOT NULL
        Schema::table('utility_accounts', function (Blueprint $table) {
            $table->string('utility_type', 20)->nullable(false)->change();
        });

        Schema::table('utility_notes', function (Blueprint $table) {
            $table->string('utility_type', 20)->nullable(false)->change();
        });

        Schema::table('utility_formatting_rules', function (Blueprint $table) {
            $table->string('utility_type', 20)->nullable(false)->change();
        });

        Schema::table('property_utility_exclusions', function (Blueprint $table) {
            $table->string('utility_type', 50)->nullable(false)->change();
        });
    }

    private function dropNewIndexes(): void
    {
        Schema::table('utility_accounts', function (Blueprint $table) {
            $table->dropIndex(['utility_type_id']);
        });

        Schema::table('utility_notes', function (Blueprint $table) {
            $table->dropUnique(['property_id', 'utility_type_id']);
            $table->dropIndex(['utility_type_id']);
        });

        Schema::table('utility_formatting_rules', function (Blueprint $table) {
            $table->dropIndex(['utility_type_id', 'enabled']);
        });

        Schema::table('property_utility_exclusions', function (Blueprint $table) {
            $table->dropUnique(['property_id', 'utility_type_id']);
            $table->dropIndex(['utility_type_id']);
        });
    }

    private function dropUtilityTypeIdColumns(): void
    {
        Schema::table('utility_accounts', function (Blueprint $table) {
            $table->dropForeign(['utility_type_id']);
            $table->dropColumn('utility_type_id');
        });

        Schema::table('utility_notes', function (Blueprint $table) {
            $table->dropForeign(['utility_type_id']);
            $table->dropColumn('utility_type_id');
        });

        Schema::table('utility_formatting_rules', function (Blueprint $table) {
            $table->dropForeign(['utility_type_id']);
            $table->dropColumn('utility_type_id');
        });

        Schema::table('property_utility_exclusions', function (Blueprint $table) {
            $table->dropForeign(['utility_type_id']);
            $table->dropColumn('utility_type_id');
        });
    }

    private function restoreOldIndexesAndConstraints(): void
    {
        Schema::table('utility_accounts', function (Blueprint $table) {
            $table->index('utility_type');
        });

        Schema::table('utility_notes', function (Blueprint $table) {
            $table->unique(['property_id', 'utility_type']);
            $table->index('utility_type');
        });

        Schema::table('utility_formatting_rules', function (Blueprint $table) {
            $table->index(['utility_type', 'enabled']);
        });

        Schema::table('property_utility_exclusions', function (Blueprint $table) {
            $table->unique(['property_id', 'utility_type']);
            $table->index('utility_type');
        });
    }
};
