<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration:
     * 1. Migrates AppFolio connection settings to the settings table
     * 2. Removes the appfolio_connection_id foreign key from sync_runs
     * 3. Drops the appfolio_connections table
     */
    public function up(): void
    {
        // First, migrate existing connection data to settings table
        $connection = DB::table('appfolio_connections')->first();

        if ($connection) {
            // Migrate connection settings
            Setting::set('appfolio', 'client_id', $connection->client_id);

            // The client_secret is already encrypted in the old table
            // We need to store it as-is since Setting::set with encrypted=true would double-encrypt
            // The value column is JSON type, so we need to JSON-encode the string
            if ($connection->client_secret_encrypted) {
                DB::table('settings')->updateOrInsert(
                    ['category' => 'appfolio', 'key' => 'client_secret'],
                    [
                        'value' => json_encode($connection->client_secret_encrypted),
                        'encrypted' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            // Extract database name from api_base_url if possible
            // Old format: https://sutro.appfolio.com or https://api.appfolio.com
            // New format: just store the database name (e.g., "sutro")
            $database = null;
            if ($connection->api_base_url) {
                if (preg_match('/https?:\/\/([^.]+)\.appfolio\.com/', $connection->api_base_url, $matches)) {
                    $database = $matches[1];
                    // Don't use "api" as database name - it was a placeholder
                    if ($database === 'api') {
                        $database = null;
                    }
                }
            }
            if ($database) {
                Setting::set('appfolio', 'database', $database);
            }
            Setting::set('appfolio', 'status', $connection->status ?? 'configured');

            if ($connection->last_success_at) {
                Setting::set('appfolio', 'last_success_at', $connection->last_success_at);
            }

            if ($connection->last_error) {
                Setting::set('appfolio', 'last_error', $connection->last_error);
            }
        }

        // Remove the foreign key constraint from sync_runs
        Schema::table('sync_runs', function (Blueprint $table) {
            $table->dropForeign(['appfolio_connection_id']);
            $table->dropColumn('appfolio_connection_id');
        });

        // Remove the foreign key constraint from sync_failure_alerts
        Schema::table('sync_failure_alerts', function (Blueprint $table) {
            $table->dropForeign(['appfolio_connection_id']);
            $table->dropUnique(['appfolio_connection_id']);
            $table->dropColumn('appfolio_connection_id');
        });

        // Drop the appfolio_connections table
        Schema::dropIfExists('appfolio_connections');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate the appfolio_connections table
        Schema::create('appfolio_connections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('client_id');
            $table->text('client_secret_encrypted')->nullable();
            $table->string('api_base_url');
            $table->string('status')->default('pending');
            $table->timestamp('last_success_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('sync_config')->nullable();
            $table->timestamps();
        });

        // Add back the foreign key to sync_runs
        Schema::table('sync_runs', function (Blueprint $table) {
            $table->foreignUuid('appfolio_connection_id')
                ->nullable()
                ->after('id')
                ->constrained('appfolio_connections')
                ->nullOnDelete();
        });

        // Add back the foreign key to sync_failure_alerts
        Schema::table('sync_failure_alerts', function (Blueprint $table) {
            $table->foreignUuid('appfolio_connection_id')
                ->nullable()
                ->after('id')
                ->constrained('appfolio_connections')
                ->cascadeOnDelete();
            $table->unique('appfolio_connection_id');
        });

        // Migrate settings back to connection
        $clientId = Setting::get('appfolio', 'client_id');

        if ($clientId) {
            $connectionId = (string) \Illuminate\Support\Str::uuid();

            // Reconstruct api_base_url from database name
            $database = Setting::get('appfolio', 'database');
            $apiBaseUrl = $database ? "https://{$database}.appfolio.com" : 'https://api.appfolio.com';

            DB::table('appfolio_connections')->insert([
                'id' => $connectionId,
                'name' => 'Primary Connection',
                'client_id' => $clientId,
                'client_secret_encrypted' => DB::table('settings')
                    ->where('category', 'appfolio')
                    ->where('key', 'client_secret')
                    ->value('value'),
                'api_base_url' => $apiBaseUrl,
                'status' => Setting::get('appfolio', 'status') ?? 'configured',
                'last_success_at' => Setting::get('appfolio', 'last_success_at'),
                'last_error' => Setting::get('appfolio', 'last_error'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Update sync_runs to point to the new connection
            DB::table('sync_runs')->update(['appfolio_connection_id' => $connectionId]);

            // Update sync_failure_alerts to point to the new connection
            DB::table('sync_failure_alerts')->update(['appfolio_connection_id' => $connectionId]);
        }

        // Remove appfolio settings
        DB::table('settings')->where('category', 'appfolio')->delete();
    }
};
