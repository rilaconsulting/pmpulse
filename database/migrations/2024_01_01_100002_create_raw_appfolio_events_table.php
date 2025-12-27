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
        Schema::create('raw_appfolio_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sync_run_id')
                ->nullable()
                ->constrained('sync_runs')
                ->onDelete('set null');
            $table->string('resource_type'); // properties, units, people, leases, etc.
            $table->string('external_id'); // AppFolio's ID for the resource
            $table->jsonb('payload_json'); // Raw JSON payload from API
            $table->timestamp('pulled_at'); // When we fetched from AppFolio
            $table->timestamp('processed_at')->nullable(); // When we normalized it
            $table->timestamps();

            // Indexes for querying and deduplication
            $table->index(['resource_type', 'external_id']);
            $table->index('pulled_at');
            $table->index('processed_at');

            // Composite unique to prevent exact duplicates
            $table->unique(['resource_type', 'external_id', 'pulled_at'], 'raw_events_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('raw_appfolio_events');
    }
};
