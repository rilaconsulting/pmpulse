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
        Schema::create('utility_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('property_id')->constrained()->cascadeOnDelete();
            $table->string('utility_type', 20);
            $table->text('note');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['property_id', 'utility_type']);
            $table->index('utility_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('utility_notes');
    }
};
