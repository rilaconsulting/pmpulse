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
        Schema::create('utility_formatting_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('utility_type', 20);
            $table->string('name', 100);
            $table->string('operator', 20);
            $table->decimal('threshold', 8, 2);
            $table->string('color', 7);
            $table->string('background_color', 7)->nullable();
            $table->smallInteger('priority')->default(0);
            $table->boolean('enabled')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['utility_type', 'enabled']);
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('utility_formatting_rules');
    }
};
