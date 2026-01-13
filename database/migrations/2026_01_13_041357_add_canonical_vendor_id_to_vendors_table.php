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
        Schema::table('vendors', function (Blueprint $table) {
            // Self-referencing FK for canonical vendor (vendor deduplication)
            // NULL = this is a canonical/primary vendor
            // UUID = this vendor is a duplicate, points to its canonical vendor
            $table->foreignUuid('canonical_vendor_id')
                ->nullable()
                ->after('id')
                ->constrained('vendors')
                ->nullOnDelete();

            $table->index('canonical_vendor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropForeign(['canonical_vendor_id']);
            $table->dropColumn('canonical_vendor_id');
        });
    }
};
