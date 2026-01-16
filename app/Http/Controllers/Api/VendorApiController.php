<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MarkVendorCanonicalRequest;
use App\Http\Requests\MarkVendorDuplicateRequest;
use App\Jobs\FindPotentialDuplicateVendorsJob;
use App\Models\Vendor;
use App\Models\VendorDuplicateAnalysis;
use App\Services\VendorDeduplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorApiController extends Controller
{
    public function __construct(
        private readonly VendorDeduplicationService $deduplicationService
    ) {}

    /**
     * Mark a vendor as a duplicate of another vendor.
     *
     * POST /api/vendors/{vendor}/mark-duplicate
     * Body: { "canonical_vendor_id": "uuid" }
     */
    public function markDuplicate(MarkVendorDuplicateRequest $request, Vendor $vendor): JsonResponse
    {
        $canonicalVendor = Vendor::findOrFail($request->validated('canonical_vendor_id'));

        $success = $vendor->markAsDuplicateOf($canonicalVendor);

        if (! $success) {
            return response()->json([
                'message' => 'Failed to mark vendor as duplicate.',
            ], 422);
        }

        $vendor->load('canonicalVendor');

        return response()->json([
            'message' => 'Vendor marked as duplicate successfully.',
            'data' => $vendor,
        ]);
    }

    /**
     * Mark a vendor as canonical (remove duplicate status).
     *
     * POST /api/vendors/{vendor}/mark-canonical
     */
    public function markCanonical(MarkVendorCanonicalRequest $request, Vendor $vendor): JsonResponse
    {
        if ($vendor->isCanonical()) {
            return response()->json([
                'message' => 'Vendor is already canonical.',
                'data' => $vendor,
            ]);
        }

        $success = $vendor->markAsCanonical();

        if (! $success) {
            return response()->json([
                'message' => 'Failed to mark vendor as canonical.',
            ], 422);
        }

        return response()->json([
            'message' => 'Vendor marked as canonical successfully.',
            'data' => $vendor,
        ]);
    }

    /**
     * Get all duplicate vendors for a canonical vendor.
     *
     * GET /api/vendors/{vendor}/duplicates
     */
    public function duplicates(Request $request, Vendor $vendor): JsonResponse
    {
        $this->authorize('admin');

        // If this is a duplicate vendor, return empty array
        if ($vendor->isDuplicate()) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'is_duplicate' => true,
                    'canonical_vendor_id' => $vendor->canonical_vendor_id,
                ],
            ]);
        }

        $duplicates = $vendor->duplicateVendors()
            ->orderBy('company_name')
            ->get();

        return response()->json([
            'data' => $duplicates,
            'meta' => [
                'is_duplicate' => false,
                'count' => $duplicates->count(),
            ],
        ]);
    }

    /**
     * Get vendors with similar names for deduplication review.
     *
     * GET /api/vendors/potential-duplicates
     */
    public function potentialDuplicates(Request $request): JsonResponse
    {
        $this->authorize('admin');

        $threshold = $request->float('threshold', 0.6);
        $limit = $request->integer('limit', 50);

        // Get all canonical vendors
        $vendors = Vendor::canonical()
            ->orderBy('company_name')
            ->get(['id', 'company_name', 'contact_name', 'email', 'phone', 'vendor_trades']);

        $potentialDuplicates = $this->deduplicationService->findDuplicatesInCollection(
            $vendors,
            $threshold,
            $limit
        );

        return response()->json([
            'data' => $potentialDuplicates,
            'meta' => [
                'total_vendors' => $vendors->count(),
                'potential_duplicates_count' => count($potentialDuplicates),
                'threshold' => $threshold,
            ],
        ]);
    }

    /**
     * Start a background job to find potential duplicate vendors.
     *
     * POST /api/vendors/duplicate-analysis
     * Body: { "threshold": 0.6, "limit": 50 }
     */
    public function startDuplicateAnalysis(Request $request): JsonResponse
    {
        $this->authorize('admin');

        $validated = $request->validate([
            'threshold' => ['sometimes', 'numeric', 'min:0.1', 'max:1.0'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:500'],
        ]);

        // Check if there's already an analysis in progress
        $pendingAnalysis = VendorDuplicateAnalysis::query()
            ->whereIn('status', ['pending', 'processing'])
            ->first();

        if ($pendingAnalysis) {
            return response()->json([
                'message' => 'An analysis is already in progress.',
                'data' => $pendingAnalysis,
            ], 409);
        }

        // Create a new analysis record
        $analysis = VendorDuplicateAnalysis::create([
            'requested_by' => auth()->id(),
            'status' => 'pending',
            'threshold' => $validated['threshold'] ?? 0.6,
            'limit' => $validated['limit'] ?? 50,
        ]);

        // Dispatch the job
        FindPotentialDuplicateVendorsJob::dispatch($analysis);

        return response()->json([
            'message' => 'Duplicate analysis started.',
            'data' => $analysis,
        ], 202);
    }

    /**
     * Get the status and results of a duplicate analysis.
     *
     * GET /api/vendors/duplicate-analysis/{analysis}
     */
    public function getDuplicateAnalysis(VendorDuplicateAnalysis $analysis): JsonResponse
    {
        $this->authorize('admin');

        return response()->json([
            'data' => $analysis,
        ]);
    }

    /**
     * Get the most recent duplicate analysis.
     *
     * GET /api/vendors/duplicate-analysis/latest
     */
    public function getLatestDuplicateAnalysis(): JsonResponse
    {
        $this->authorize('admin');

        $analysis = VendorDuplicateAnalysis::query()
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $analysis) {
            return response()->json([
                'message' => 'No analysis found.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'data' => $analysis,
        ]);
    }

    /**
     * Authorize admin access.
     */
    private function authorize(string $ability): void
    {
        if (! auth()->user()?->isAdmin()) {
            abort(403, 'This action requires administrator privileges.');
        }
    }
}
