<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreUtilityNoteRequest;
use App\Models\Property;
use App\Models\UtilityAccount;
use App\Models\UtilityNote;
use Illuminate\Http\JsonResponse;

class UtilityNoteController extends Controller
{
    /**
     * Valid utility types (cached for the request).
     */
    private ?array $validUtilityTypes = null;

    /**
     * Get the note for a specific property and utility type.
     */
    public function show(Property $property, string $utilityType): JsonResponse
    {
        if (! $this->isValidUtilityType($utilityType)) {
            return response()->json([
                'message' => 'Invalid utility type.',
            ], 422);
        }

        $note = UtilityNote::query()
            ->forProperty($property->id)
            ->ofType($utilityType)
            ->with('creator:id,name')
            ->first();

        if (! $note) {
            return response()->json(['note' => null]);
        }

        return response()->json([
            'note' => [
                'id' => $note->id,
                'note' => $note->note,
                'utility_type' => $note->utility_type,
                'created_by' => $note->creator?->name ?? 'Unknown',
                'updated_at' => $note->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Create or update a note for a property (upsert).
     */
    public function store(StoreUtilityNoteRequest $request, Property $property): JsonResponse
    {
        $validated = $request->validated();

        $note = UtilityNote::updateOrCreate(
            [
                'property_id' => $property->id,
                'utility_type' => $validated['utility_type'],
            ],
            [
                'note' => $validated['note'],
                'created_by' => $request->user()->id,
            ]
        );

        $note->load('creator:id,name');

        return response()->json([
            'note' => [
                'id' => $note->id,
                'note' => $note->note,
                'utility_type' => $note->utility_type,
                'created_by' => $note->creator?->name ?? 'Unknown',
                'updated_at' => $note->updated_at->toIso8601String(),
            ],
            'message' => 'Note saved successfully.',
        ]);
    }

    /**
     * Delete a note for a specific property and utility type.
     */
    public function destroy(Property $property, string $utilityType): JsonResponse
    {
        if (! $this->isValidUtilityType($utilityType)) {
            return response()->json([
                'message' => 'Invalid utility type.',
            ], 422);
        }

        $deleted = UtilityNote::query()
            ->forProperty($property->id)
            ->ofType($utilityType)
            ->delete();

        if (! $deleted) {
            return response()->json([
                'message' => 'Note not found.',
            ], 404);
        }

        return response()->json([
            'message' => 'Note deleted successfully.',
        ]);
    }

    /**
     * Check if the given utility type is valid.
     */
    private function isValidUtilityType(string $utilityType): bool
    {
        if ($this->validUtilityTypes === null) {
            $this->validUtilityTypes = array_keys(UtilityAccount::getUtilityTypeOptions());
        }

        return in_array($utilityType, $this->validUtilityTypes, true);
    }
}
