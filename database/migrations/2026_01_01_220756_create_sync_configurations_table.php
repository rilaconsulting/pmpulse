<?php

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
        Schema::create('sync_configurations', function (Blueprint $table) {
            $table->id();
            $table->boolean('business_hours_enabled')->default(true);
            $table->string('timezone')->default('America/Los_Angeles');
            $table->unsignedTinyInteger('start_hour')->default(9);
            $table->unsignedTinyInteger('end_hour')->default(17);
            $table->boolean('weekdays_only')->default(true);
            $table->unsignedSmallInteger('business_hours_interval')->default(15);
            $table->unsignedSmallInteger('off_hours_interval')->default(60);
            $table->string('full_sync_time')->default('02:00');
            $table->timestamps();
        });

        // Insert default configuration
        DB::table('sync_configurations')->insert([
            'business_hours_enabled' => true,
            'timezone' => 'America/Los_Angeles',
            'start_hour' => 9,
            'end_hour' => 17,
            'weekdays_only' => true,
            'business_hours_interval' => 15,
            'off_hours_interval' => 60,
            'full_sync_time' => '02:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_configurations');
    }
};
