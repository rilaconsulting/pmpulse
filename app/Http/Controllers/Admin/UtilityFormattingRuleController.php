<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUtilityFormattingRuleRequest;
use App\Http\Requests\UpdateUtilityFormattingRuleRequest;
use App\Models\UtilityFormattingRule;
use App\Models\UtilityType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UtilityFormattingRuleController extends Controller
{
    /**
     * Display a listing of the formatting rules.
     */
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $rules = UtilityFormattingRule::query()
            ->with(['creator:id,name', 'utilityType'])
            ->join('utility_types', 'utility_formatting_rules.utility_type_id', '=', 'utility_types.id')
            ->orderBy('utility_types.sort_order')
            ->orderByDesc('utility_formatting_rules.priority')
            ->select('utility_formatting_rules.*')
            ->get()
            ->map(fn (UtilityFormattingRule $rule) => [
                'id' => $rule->id,
                'utility_type_id' => $rule->utility_type_id,
                'utility_type_key' => $rule->utilityType?->key,
                'utility_type_label' => $rule->utilityType?->label ?? 'Unknown',
                'name' => $rule->name,
                'operator' => $rule->operator,
                'operator_label' => $rule->operator_label,
                'threshold' => $rule->threshold,
                'color' => $rule->color,
                'background_color' => $rule->background_color,
                'priority' => $rule->priority,
                'enabled' => $rule->enabled,
                'created_by' => $rule->creator?->name ?? 'Unknown',
                'created_at' => $rule->created_at->toIso8601String(),
                'updated_at' => $rule->updated_at->toIso8601String(),
            ]);

        // Group rules by utility type key
        $rulesByType = $rules->groupBy('utility_type_key');

        return Inertia::render('Admin/UtilityFormattingRules', [
            'rules' => $rules,
            'rulesByType' => $rulesByType,
            'utilityTypes' => UtilityType::getAllWithMetadata(),
            'operators' => UtilityFormattingRule::OPERATORS,
        ]);
    }

    /**
     * Store a newly created formatting rule.
     */
    public function store(StoreUtilityFormattingRuleRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        UtilityFormattingRule::create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.utility-formatting-rules.index')
            ->with('success', 'Formatting rule created successfully.');
    }

    /**
     * Update the specified formatting rule.
     */
    public function update(UpdateUtilityFormattingRuleRequest $request, UtilityFormattingRule $utilityFormattingRule): RedirectResponse
    {
        $validated = $request->validated();

        $utilityFormattingRule->update($validated);

        return redirect()->route('admin.utility-formatting-rules.index')
            ->with('success', 'Formatting rule updated successfully.');
    }

    /**
     * Remove the specified formatting rule.
     */
    public function destroy(Request $request, UtilityFormattingRule $utilityFormattingRule): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $utilityFormattingRule->delete();

        return redirect()->route('admin.utility-formatting-rules.index')
            ->with('success', 'Formatting rule deleted successfully.');
    }
}
