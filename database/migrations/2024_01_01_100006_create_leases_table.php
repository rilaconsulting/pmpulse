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
        Schema::create('leases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('external_id')->unique(); // AppFolio lease ID
            $table->foreignUuid('unit_id')
                ->constrained('units')
                ->cascadeOnDelete();
            $table->foreignUuid('person_id')
                ->nullable()
                ->constrained('people')
                ->nullOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('rent', 10, 2);
            $table->decimal('security_deposit', 10, 2)->nullable();
            $table->string('status')->default('active'); // active, past, future, etc.
            $table->timestamps();

            // Indexes
            $table->index('status');
            $table->index('start_date');
            $table->index('end_date');
            $table->index(['unit_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leases');
    }
};
