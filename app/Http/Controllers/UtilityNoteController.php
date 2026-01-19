<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreUtilityNoteRequest;
use App\Models\Property;
use App\Models\UtilityNote;
use App\Models\UtilityType;
use Illuminate\Http\JsonResponse;

class UtilityNoteController extends Controller
{
    /**
     * Valid utility type keys (cached for the request).
     */
    private ?array $validUtilityTypeKeys = null;

    /**
     * Get the note for a specific property and utility type.
     */
    public function show(Property $property, string $utilityType): JsonResponse
    {
        if (! $this->isValidUtilityTypeKey($utilityType)) {
            return response()->json([
                'message' => 'Invalid utility type.',
            ], 422);
        }

        $note = UtilityNote::query()
            ->forProperty($property->id)
            ->ofTypeKey($utilityType)
            ->with(['creator:id,name', 'utilityType'])
            ->first();

        if (! $note) {
            return response()->json(['note' => null]);
        }

        return response()->json([
            'note' => [
                'id' => $note->id,
                'note' => $note->note,
                'utility_type_id' => $note->utility_type_id,
                'utility_type_key' => $note->utilityType?->key,
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
                'utility_type_id' => $validated['utility_type_id'],
            ],
            [
                'note' => $validated['note'],
                'created_by' => $request->user()->id,
            ]
        );

        $note->load(['creator:id,name', 'utilityType']);

        return response()->json([
            'note' => [
                'id' => $note->id,
                'note' => $note->note,
                'utility_type_id' => $note->utility_type_id,
                'utility_type_key' => $note->utilityType?->key,
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
        if (! $this->isValidUtilityTypeKey($utilityType)) {
            return response()->json([
                'message' => 'Invalid utility type.',
            ], 422);
        }

        $deleted = UtilityNote::query()
            ->forProperty($property->id)
            ->ofTypeKey($utilityType)
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
     * Check if the given utility type key is valid.
     */
    private function isValidUtilityTypeKey(string $typeKey): bool
    {
        if ($this->validUtilityTypeKeys === null) {
            $this->validUtilityTypeKeys = UtilityType::pluck('key')->toArray();
        }

        return in_array($typeKey, $this->validUtilityTypeKeys, true);
    }
}
