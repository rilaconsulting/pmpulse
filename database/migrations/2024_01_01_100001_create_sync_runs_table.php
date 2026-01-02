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
        Schema::create('sync_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('appfolio_connection_id')
                ->constrained('appfolio_connections')
                ->cascadeOnDelete();
            $table->string('mode'); // full, incremental
            $table->string('status')->default('pending'); // pending, running, completed, failed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('resources_synced')->default(0);
            $table->unsignedInteger('errors_count')->default(0);
            $table->text('error_summary')->nullable();
            $table->json('metadata')->nullable(); // Additional run info
            $table->timestamps();

            // Indexes for common queries
            $table->index(['status', 'started_at']);
            $table->index('started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_runs');
    }
};
