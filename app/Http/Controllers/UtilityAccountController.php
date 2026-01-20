<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreUtilityAccountRequest;
use App\Http\Requests\UpdateUtilityAccountRequest;
use App\Models\UtilityAccount;
use App\Models\UtilityType;
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
            ->with('utilityType')
            ->orderBy('gl_account_number')
            ->get();

        return Inertia::render('Admin/UtilityAccounts', [
            'accounts' => $accounts,
            'utilityTypes' => UtilityType::getAllWithMetadata(),
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
            'utilityTypes' => UtilityType::getAllWithMetadata(),
        ]);
    }

    /**
     * Display the utility types configuration page.
     */
    public function types(Request $request): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        // Eager load accounts with expense counts to avoid N+1 queries
        $types = UtilityType::ordered()
            ->withCount('accounts')
            ->with(['accounts' => fn ($query) => $query->withCount('utilityExpenses')])
            ->get();

        $typesWithCounts = $types->map(function (UtilityType $type) {
            // Sum expense counts from eager loaded accounts
            $expenseCount = $type->accounts->sum('utility_expenses_count');

            return [
                'id' => $type->id,
                'key' => $type->key,
                'label' => $type->label,
                'icon' => $type->icon_or_default,
                'color_scheme' => $type->color_scheme_or_default,
                'sort_order' => $type->sort_order,
                'is_system' => $type->is_system,
                'accounts_count' => $type->accounts_count,
                'expenses_count' => $expenseCount,
            ];
        });

        return Inertia::render('Admin/UtilityTypes', [
            'utilityTypes' => $typesWithCounts,
        ]);
    }

    /**
     * Add a new utility type.
     */
    public function storeType(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'key' => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:utility_types,key'],
            'label' => ['required', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color_scheme' => ['nullable', 'string', 'max:20'],
        ], [
            'key.regex' => 'The key must start with a letter and contain only lowercase letters, numbers, and underscores.',
            'key.unique' => 'A utility type with this key already exists.',
        ]);

        // Get the max sort order and add 1
        $maxSortOrder = UtilityType::max('sort_order') ?? 0;

        UtilityType::create([
            'key' => $validated['key'],
            'label' => $validated['label'],
            'icon' => $validated['icon'] ?? UtilityType::DEFAULT_ICON,
            'color_scheme' => $validated['color_scheme'] ?? UtilityType::DEFAULT_COLOR_SCHEME,
            'sort_order' => $maxSortOrder + 1,
            'is_system' => false,
        ]);

        return back()->with('success', 'Utility type added successfully.');
    }

    /**
     * Update a utility type.
     */
    public function updateType(Request $request, UtilityType $utilityType): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color_scheme' => ['nullable', 'string', 'max:20'],
        ]);

        $utilityType->update([
            'label' => $validated['label'],
            'icon' => $validated['icon'] ?? $utilityType->icon,
            'color_scheme' => $validated['color_scheme'] ?? $utilityType->color_scheme,
        ]);

        return back()->with('success', 'Utility type updated successfully.');
    }

    /**
     * Remove a utility type.
     */
    public function destroyType(Request $request, UtilityType $utilityType): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        // Check if type is in use by any related records
        if ($utilityType->isInUse()) {
            return back()->with('error', "Cannot delete '{$utilityType->label}'. It is in use by account mappings, notes, formatting rules, or property exclusions.");
        }

        try {
            $utilityType->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle FK constraint violations that may occur due to race conditions
            return back()->with('error', "Cannot delete '{$utilityType->label}'. It is still in use by related records.");
        }

        return back()->with('success', 'Utility type deleted successfully.');
    }

    /**
     * Reset utility types to defaults (remove custom types).
     */
    public function resetTypes(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        // Check if any custom types are in use by related records
        $customTypes = UtilityType::custom()->get();

        foreach ($customTypes as $type) {
            if ($type->isInUse()) {
                return back()->with('error', "Cannot reset to defaults. Custom type '{$type->label}' is in use by related records.");
            }
        }

        try {
            // Delete all custom types
            UtilityType::custom()->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle FK constraint violations that may occur due to race conditions
            return back()->with('error', 'Cannot reset to defaults. Some custom types are still in use.');
        }

        return back()->with('success', 'Custom utility types removed. Only system types remain.');
    }
}
