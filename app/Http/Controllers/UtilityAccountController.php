<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\UtilityAccount;
use App\Services\UtilityExpenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UtilityAccountController extends Controller
{
    /**
     * Display a listing of utility accounts.
     */
    public function index(Request $request): Response
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

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
    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'gl_account_number' => ['required', 'string', 'max:50', 'unique:utility_accounts,gl_account_number'],
            'gl_account_name' => ['required', 'string', 'max:255'],
            'utility_type' => ['required', 'string', Rule::in(array_keys(UtilityAccount::UTILITY_TYPES))],
            'is_active' => ['boolean'],
        ]);

        UtilityAccount::create([
            ...$validated,
            'is_active' => $validated['is_active'] ?? true,
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'Utility account mapping created successfully.');
    }

    /**
     * Update the specified utility account.
     */
    public function update(Request $request, UtilityAccount $utilityAccount): RedirectResponse
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'gl_account_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('utility_accounts', 'gl_account_number')->ignore($utilityAccount->id),
            ],
            'gl_account_name' => ['required', 'string', 'max:255'],
            'utility_type' => ['required', 'string', Rule::in(array_keys(UtilityAccount::UTILITY_TYPES))],
            'is_active' => ['boolean'],
        ]);

        $utilityAccount->update($validated);

        return back()->with('success', 'Utility account mapping updated successfully.');
    }

    /**
     * Remove the specified utility account.
     */
    public function destroy(UtilityAccount $utilityAccount): RedirectResponse
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $utilityAccount->delete();

        return back()->with('success', 'Utility account mapping deleted successfully.');
    }

    /**
     * Get suggestions for unmapped GL accounts.
     */
    public function suggestions(UtilityExpenseService $utilityExpenseService): Response
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $unmatched = $utilityExpenseService->getUnmatchedAccounts(90);

        return Inertia::render('Admin/UtilityAccountSuggestions', [
            'unmatchedAccounts' => $unmatched,
            'utilityTypes' => UtilityAccount::getUtilityTypeOptions(),
        ]);
    }
}
