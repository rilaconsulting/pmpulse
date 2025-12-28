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
        Schema::create('alert_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('metric'); // vacancy_count, delinquency_amount, work_order_days_open
            $table->string('operator'); // gt, gte, lt, lte, eq
            $table->decimal('threshold', 12, 2);
            $table->boolean('enabled')->default(true);
            $table->json('recipients'); // Array of email addresses
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            // Index
            $table->index('enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alert_rules');
    }
};
