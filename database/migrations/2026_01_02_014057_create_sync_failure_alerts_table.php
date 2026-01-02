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
        Schema::create('sync_failure_alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('appfolio_connection_id')->constrained()->cascadeOnDelete();
            $table->integer('consecutive_failures')->default(0);
            $table->timestamp('last_alert_sent_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignUuid('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('failure_details')->nullable();
            $table->timestamps();

            $table->unique('appfolio_connection_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_failure_alerts');
    }
};
