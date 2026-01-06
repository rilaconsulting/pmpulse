<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreUtilityAccountRequest;
use App\Http\Requests\UpdateUtilityAccountRequest;
use App\Models\UtilityAccount;
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

        // Check if there are any utility expenses linked to this account
        $expenseCount = $utilityAccount->utilityExpenses()->count();

        if ($expenseCount > 0) {
            return back()->with('error', "Cannot delete this mapping. There are {$expenseCount} utility expense(s) linked to this account. Consider deactivating the mapping instead.");
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

    /**
     * Display the utility types configuration page.
     */
    public function types(Request $request): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $types = UtilityAccount::getUtilityTypeOptions();

        // Get usage counts for each type (expenses counted via account relationship)
        $typeCounts = [];
        foreach (array_keys($types) as $typeKey) {
            $accounts = UtilityAccount::where('utility_type', $typeKey)->get();
            $expenseCount = $accounts->sum(fn ($account) => $account->utilityExpenses()->count());

            $typeCounts[$typeKey] = [
                'accounts' => $accounts->count(),
                'expenses' => $expenseCount,
            ];
        }

        return Inertia::render('Admin/UtilityTypes', [
            'utilityTypes' => $types,
            'typeCounts' => $typeCounts,
            'defaultTypes' => UtilityAccount::DEFAULT_UTILITY_TYPES,
        ]);
    }

    /**
     * Add a new utility type.
     */
    public function storeType(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'key' => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/'],
            'label' => ['required', 'string', 'max:100'],
        ], [
            'key.regex' => 'The key must start with a letter and contain only lowercase letters, numbers, and underscores.',
        ]);

        $types = UtilityAccount::getUtilityTypeOptions();

        if (isset($types[$validated['key']])) {
            return back()->with('error', 'A utility type with this key already exists.');
        }

        UtilityAccount::addUtilityType($validated['key'], $validated['label']);

        return back()->with('success', 'Utility type added successfully.');
    }

    /**
     * Update a utility type label.
     */
    public function updateType(Request $request, string $key): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:100'],
        ]);

        $types = UtilityAccount::getUtilityTypeOptions();

        if (! isset($types[$key])) {
            return back()->with('error', 'Utility type not found.');
        }

        UtilityAccount::updateUtilityTypeLabel($key, $validated['label']);

        return back()->with('success', 'Utility type updated successfully.');
    }

    /**
     * Remove a utility type.
     */
    public function destroyType(Request $request, string $key): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $types = UtilityAccount::getUtilityTypeOptions();

        if (! isset($types[$key])) {
            return back()->with('error', 'Utility type not found.');
        }

        // Check if type is in use by any accounts
        $accountCount = UtilityAccount::where('utility_type', $key)->count();

        if ($accountCount > 0) {
            return back()->with('error', "Cannot delete '{$types[$key]}'. It is used by {$accountCount} account mapping(s). Delete or reassign those accounts first.");
        }

        UtilityAccount::removeUtilityType($key);

        return back()->with('success', 'Utility type deleted successfully.');
    }

    /**
     * Reset utility types to defaults.
     */
    public function resetTypes(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        // Check if any custom types are in use by accounts
        $defaultKeys = array_keys(UtilityAccount::DEFAULT_UTILITY_TYPES);
        $currentTypes = UtilityAccount::getUtilityTypeOptions();
        $customKeys = array_diff(array_keys($currentTypes), $defaultKeys);

        foreach ($customKeys as $key) {
            $accountCount = UtilityAccount::where('utility_type', $key)->count();

            if ($accountCount > 0) {
                return back()->with('error', "Cannot reset to defaults. Custom type '{$currentTypes[$key]}' is used by {$accountCount} account(s).");
            }
        }

        UtilityAccount::setUtilityTypeOptions(UtilityAccount::DEFAULT_UTILITY_TYPES);

        return back()->with('success', 'Utility types reset to defaults.');
    }
}
