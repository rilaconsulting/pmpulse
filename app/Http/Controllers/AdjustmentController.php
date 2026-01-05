<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreAdjustmentRequest;
use App\Http\Requests\UpdateAdjustmentRequest;
use App\Models\Property;
use App\Models\PropertyAdjustment;
use App\Services\AdjustmentService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;

class AdjustmentController extends Controller
{
    public function __construct(
        private readonly AdjustmentService $adjustmentService
    ) {}

    /**
     * Store a new adjustment for a property.
     */
    public function store(StoreAdjustmentRequest $request, Property $property): RedirectResponse
    {
        $validated = $request->validated();

        $this->adjustmentService->createAdjustment(
            property: $property,
            field: $validated['field_name'],
            adjustedValue: $validated['adjusted_value'],
            effectiveFrom: Carbon::parse($validated['effective_from']),
            effectiveTo: isset($validated['effective_to']) ? Carbon::parse($validated['effective_to']) : null,
            reason: $validated['reason'],
            createdBy: $request->user()->id
        );

        return back()->with('success', 'Adjustment created successfully.');
    }

    /**
     * Update an existing adjustment.
     */
    public function update(UpdateAdjustmentRequest $request, Property $property, PropertyAdjustment $adjustment): RedirectResponse
    {
        // Ensure the adjustment belongs to the property
        if ($adjustment->property_id !== $property->id) {
            abort(404);
        }

        $validated = $request->validated();

        $adjustment->update([
            'adjusted_value' => (string) $validated['adjusted_value'],
            'effective_to' => isset($validated['effective_to']) ? Carbon::parse($validated['effective_to']) : null,
            'reason' => $validated['reason'],
        ]);

        return back()->with('success', 'Adjustment updated successfully.');
    }

    /**
     * End a permanent adjustment by setting its effective_to to today.
     */
    public function end(Property $property, PropertyAdjustment $adjustment): RedirectResponse
    {
        // Only admins can end adjustments
        if (! request()->user()?->isAdmin()) {
            abort(403);
        }

        // Ensure the adjustment belongs to the property
        if ($adjustment->property_id !== $property->id) {
            abort(404);
        }

        // Only permanent adjustments can be ended
        if (! $adjustment->isPermanent()) {
            return back()->withErrors(['adjustment' => 'This adjustment already has an end date.']);
        }

        $this->adjustmentService->endAdjustment($adjustment);

        return back()->with('success', 'Adjustment ended successfully.');
    }

    /**
     * Delete an adjustment.
     */
    public function destroy(Property $property, PropertyAdjustment $adjustment): RedirectResponse
    {
        // Only admins can delete adjustments
        if (! request()->user()?->isAdmin()) {
            abort(403);
        }

        // Ensure the adjustment belongs to the property
        if ($adjustment->property_id !== $property->id) {
            abort(404);
        }

        $adjustment->delete();

        return back()->with('success', 'Adjustment deleted successfully.');
    }
}
