<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UtilityFormattingRule;
use Illuminate\Support\Collection;

/**
 * Utility Formatting Service
 *
 * Applies conditional formatting rules to utility data based on
 * comparison against 12-month averages.
 */
class UtilityFormattingService
{
    /**
     * Request-scoped cache for formatting rules per utility type.
     *
     * @var array<string, Collection<UtilityFormattingRule>>
     */
    private array $rulesCache = [];

    /**
     * Get formatting for a value compared against an average.
     *
     * Returns formatting data if any enabled rule matches, null otherwise.
     * Rules are evaluated in priority order (highest first).
     *
     * @param  string  $utilityType  The utility type (water, electric, gas, etc.)
     * @param  float|null  $value  The current value to evaluate
     * @param  float|null  $average  The 12-month average to compare against
     * @return array{color: string, background_color: string|null, rule_name: string}|null
     */
    public function getFormatting(string $utilityType, ?float $value, ?float $average): ?array
    {
        if ($value === null || $average === null || $average <= 0) {
            return null;
        }

        $rules = $this->getRulesForUtilityType($utilityType);

        foreach ($rules as $rule) {
            if ($rule->evaluate($value, $average)) {
                return [
                    'color' => $rule->color,
                    'background_color' => $rule->background_color,
                    'rule_name' => $rule->name,
                ];
            }
        }

        return null;
    }

    /**
     * Apply formatting to property comparison data.
     *
     * Adds formatting metadata to the value columns (current_month, prev_month, prev_3_months)
     * by comparing each value against the 12-month average.
     *
     * @param  array  $propertyData  Property data from getPropertyComparisonDataBulk
     * @param  string  $utilityType  The utility type
     * @return array Property data with formatting metadata added
     */
    public function applyFormattingToProperty(array $propertyData, string $utilityType): array
    {
        $average = $propertyData['prev_12_months'] ?? null;

        // Columns to apply formatting to
        $columnsToFormat = ['current_month', 'prev_month', 'prev_3_months'];

        $formatting = [];
        foreach ($columnsToFormat as $column) {
            $value = $propertyData[$column] ?? null;
            $columnFormatting = $this->getFormatting($utilityType, $value, $average);

            if ($columnFormatting !== null) {
                $formatting[$column] = $columnFormatting;
            }
        }

        // Add formatting metadata to the property data
        if (! empty($formatting)) {
            $propertyData['formatting'] = $formatting;
        }

        return $propertyData;
    }

    /**
     * Apply formatting to all properties in a comparison data set.
     *
     * @param  array  $comparisonData  Data from getPropertyComparisonDataBulk
     * @param  string  $utilityType  The utility type
     * @return array Comparison data with formatting applied to all properties
     */
    public function applyFormattingToComparison(array $comparisonData, string $utilityType): array
    {
        $comparisonData['properties'] = array_map(
            fn (array $property) => $this->applyFormattingToProperty($property, $utilityType),
            $comparisonData['properties']
        );

        return $comparisonData;
    }

    /**
     * Get enabled formatting rules for a utility type, ordered by priority.
     *
     * Results are cached within the request lifecycle.
     *
     * @param  string  $utilityType  The utility type
     * @return Collection<UtilityFormattingRule>
     */
    private function getRulesForUtilityType(string $utilityType): Collection
    {
        if (! isset($this->rulesCache[$utilityType])) {
            $this->rulesCache[$utilityType] = UtilityFormattingRule::query()
                ->enabled()
                ->forUtilityTypeKey($utilityType)
                ->byPriority()
                ->get();
        }

        return $this->rulesCache[$utilityType];
    }

    /**
     * Clear the rules cache.
     *
     * Useful for testing or when rules are modified during a request.
     */
    public function clearCache(): void
    {
        $this->rulesCache = [];
    }
}
