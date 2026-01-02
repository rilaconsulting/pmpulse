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
        Schema::table('users', function (Blueprint $table) {
            $table->string('auth_provider')->default('password')->after('email');
            $table->string('google_id')->nullable()->unique()->after('auth_provider');
            $table->boolean('is_active')->default(true)->after('google_id');
            $table->boolean('force_sso')->default(false)->after('is_active');
            $table->foreignUuid('role_id')
                ->nullable()
                ->after('force_sso')
                ->constrained('user_roles')
                ->nullOnDelete();
            $table->foreignUuid('created_by')
                ->nullable()
                ->after('role_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->index('auth_provider');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropForeign(['created_by']);
            $table->dropIndex(['auth_provider']);
            $table->dropIndex(['is_active']);
            $table->dropColumn([
                'auth_provider',
                'google_id',
                'is_active',
                'force_sso',
                'role_id',
                'created_by',
            ]);
        });
    }
};
