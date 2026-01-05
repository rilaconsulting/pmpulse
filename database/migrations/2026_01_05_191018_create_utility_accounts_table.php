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
        Schema::create('utility_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('gl_account_number', 50);
            $table->string('gl_account_name');
            $table->string('utility_type', 20); // water, electric, gas, garbage, sewer, other
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Unique constraint on GL account number
            $table->unique('gl_account_number');

            // Indexes for efficient querying
            $table->index('utility_type');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('utility_accounts');
    }
};
