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
        Schema::create('appfolio_connections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->default('Primary Connection');
            $table->string('client_id')->nullable();
            $table->text('client_secret_encrypted')->nullable();
            $table->string('api_base_url')->default('https://api.appfolio.com');
            $table->string('status')->default('not_configured'); // not_configured, configured, connected, error
            $table->timestamp('last_success_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('sync_config')->nullable(); // Store sync preferences
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appfolio_connections');
    }
};
