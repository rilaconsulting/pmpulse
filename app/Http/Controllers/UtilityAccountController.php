<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreUtilityAccountRequest;
use App\Http\Requests\UpdateUtilityAccountRequest;
use App\Models\UtilityAccount;
use App\Models\UtilityExpense;
use App\Services\UtilityExpenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UtilityAccountController extends Controller
{
    /**
     * Display a listing of utility accounts.
     */
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $accounts = UtilityAccount::query()
            ->orderBy('gl_account_number')
            ->get();

        return Inertia::render('Admin/UtilityAccounts', [
            'accounts' => $accounts,
            'utilityTypes' => UtilityAccount::getUtilityTypeOptions(),
        ]);
    }

    /**
     * Store a newly created utility account.
     */
    public function store(StoreUtilityAccountRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        UtilityAccount::create([
            ...$validated,
            'is_active' => $validated['is_active'] ?? true,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Utility account mapping created successfully.');
    }

    /**
     * Update the specified utility account.
     */
    public function update(UpdateUtilityAccountRequest $request, UtilityAccount $utilityAccount): RedirectResponse
    {
        $utilityAccount->update($request->validated());

        return back()->with('success', 'Utility account mapping updated successfully.');
    }

    /**
     * Remove the specified utility account.
     */
    public function destroy(Request $request, UtilityAccount $utilityAccount): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        // Check if there are any utility expenses using this account mapping
        $expenseCount = UtilityExpense::where('gl_account_number', $utilityAccount->gl_account_number)->count();

        if ($expenseCount > 0) {
            return back()->with('error', "Cannot delete this mapping. There are {$expenseCount} utility expense(s) using this GL account. Consider deactivating the mapping instead.");
        }

        $utilityAccount->delete();

        return back()->with('success', 'Utility account mapping deleted successfully.');
    }

    /**
     * Get suggestions for unmapped GL accounts.
     */
    public function suggestions(Request $request, UtilityExpenseService $utilityExpenseService): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $unmatched = $utilityExpenseService->getUnmatchedAccounts(90);

        return Inertia::render('Admin/UtilityAccountSuggestions', [
            'unmatchedAccounts' => $unmatched,
            'utilityTypes' => UtilityAccount::getUtilityTypeOptions(),
        ]);
    }
}
