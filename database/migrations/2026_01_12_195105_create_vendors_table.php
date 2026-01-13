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
        Schema::create('vendors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('external_id')->unique();
            $table->string('company_name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address_street')->nullable();
            $table->string('address_city')->nullable();
            $table->string('address_state')->nullable();
            $table->string('address_zip')->nullable();
            $table->string('vendor_type')->nullable();
            $table->string('vendor_trades')->nullable();
            $table->date('workers_comp_expires')->nullable();
            $table->date('liability_ins_expires')->nullable();
            $table->date('auto_ins_expires')->nullable();
            $table->date('state_lic_expires')->nullable();
            $table->boolean('do_not_use')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('company_name');
            $table->index('is_active');
            $table->index('do_not_use');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
