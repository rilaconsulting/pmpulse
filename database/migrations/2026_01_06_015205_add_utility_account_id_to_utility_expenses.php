<?php

use App\Models\UtilityAccount;
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
        // Step 1: Add new columns
        Schema::table('utility_expenses', function (Blueprint $table) {
            $table->foreignUuid('utility_account_id')
                ->nullable()
                ->after('property_id')
                ->constrained('utility_accounts')
                ->nullOnDelete();

            $table->string('gl_account_number', 50)
                ->nullable()
                ->after('utility_account_id');

            $table->index('utility_account_id');
        });

        // Step 2: Backfill existing data
        // Match expenses to utility accounts based on utility_type
        $accounts = UtilityAccount::all()->keyBy('utility_type');

        DB::table('utility_expenses')
            ->whereNull('utility_account_id')
            ->orderBy('id')
            ->chunk(1000, function ($expenses) use ($accounts) {
                foreach ($expenses as $expense) {
                    $account = $accounts->get($expense->utility_type);

                    if ($account) {
                        DB::table('utility_expenses')
                            ->where('id', $expense->id)
                            ->update([
                                'utility_account_id' => $account->id,
                                'gl_account_number' => $account->gl_account_number,
                            ]);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('utility_expenses', function (Blueprint $table) {
            $table->dropForeign(['utility_account_id']);
            $table->dropIndex(['utility_account_id']);
            $table->dropColumn(['utility_account_id', 'gl_account_number']);
        });
    }
};
