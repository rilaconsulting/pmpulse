<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VendorApiController extends Controller
{
    /**
     * Mark a vendor as a duplicate of another vendor.
     *
     * POST /api/vendors/{vendor}/mark-duplicate
     * Body: { "canonical_vendor_id": "uuid" }
     */
    public function markDuplicate(Request $request, Vendor $vendor): JsonResponse
    {
        $this->authorize('admin');

        $validated = $request->validate([
            'canonical_vendor_id' => ['required', 'uuid', 'exists:vendors,id'],
        ]);

        $canonicalVendor = Vendor::findOrFail($validated['canonical_vendor_id']);

        // Validation: prevent circular references
        if ($canonicalVendor->id === $vendor->id) {
            throw ValidationException::withMessages([
                'canonical_vendor_id' => ['A vendor cannot be marked as a duplicate of itself.'],
            ]);
        }

        // Validation: prevent marking a canonical vendor that has duplicates as a duplicate
        if ($vendor->duplicateVendors()->exists()) {
            throw ValidationException::withMessages([
                'canonical_vendor_id' => ['This vendor has duplicates linked to it. Reassign those duplicates first.'],
            ]);
        }

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
    public function markCanonical(Request $request, Vendor $vendor): JsonResponse
    {
        $this->authorize('admin');

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

        $potentialDuplicates = [];
        $processedPairs = [];

        foreach ($vendors as $i => $vendor1) {
            foreach ($vendors->slice($i + 1) as $vendor2) {
                $pairKey = $vendor1->id < $vendor2->id
                    ? "{$vendor1->id}:{$vendor2->id}"
                    : "{$vendor2->id}:{$vendor1->id}";

                if (isset($processedPairs[$pairKey])) {
                    continue;
                }

                $similarity = $this->calculateSimilarity($vendor1, $vendor2);

                if ($similarity >= $threshold) {
                    $potentialDuplicates[] = [
                        'vendor1' => $vendor1,
                        'vendor2' => $vendor2,
                        'similarity' => round($similarity, 3),
                        'match_reasons' => $this->getMatchReasons($vendor1, $vendor2),
                    ];
                    $processedPairs[$pairKey] = true;
                }
            }
        }

        // Sort by similarity descending
        usort($potentialDuplicates, fn ($a, $b) => $b['similarity'] <=> $a['similarity']);

        // Limit results
        $potentialDuplicates = array_slice($potentialDuplicates, 0, $limit);

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
     * Calculate similarity score between two vendors.
     */
    private function calculateSimilarity(Vendor $vendor1, Vendor $vendor2): float
    {
        $scores = [];

        // Company name similarity (highest weight)
        if ($vendor1->company_name && $vendor2->company_name) {
            $name1 = $this->normalizeString($vendor1->company_name);
            $name2 = $this->normalizeString($vendor2->company_name);

            similar_text($name1, $name2, $nameSimilarity);
            $scores[] = $nameSimilarity / 100 * 0.5; // 50% weight
        }

        // Phone match (exact)
        if ($vendor1->phone && $vendor2->phone) {
            $phone1 = preg_replace('/\D/', '', $vendor1->phone);
            $phone2 = preg_replace('/\D/', '', $vendor2->phone);
            if ($phone1 === $phone2 && strlen($phone1) >= 10) {
                $scores[] = 0.25; // 25% weight
            }
        }

        // Email match (exact or domain)
        if ($vendor1->email && $vendor2->email) {
            $email1 = strtolower($vendor1->email);
            $email2 = strtolower($vendor2->email);

            if ($email1 === $email2) {
                $scores[] = 0.15; // 15% weight for exact match
            } else {
                $pos1 = strrpos($email1, '@');
                $pos2 = strrpos($email2, '@');

                if ($pos1 !== false && $pos2 !== false) {
                    $domain1 = substr($email1, $pos1 + 1);
                    $domain2 = substr($email2, $pos2 + 1);

                    if ($domain1 !== '' && $domain1 === $domain2 && ! in_array($domain1, ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com'])) {
                        $scores[] = 0.05; // 5% weight for same company domain
                    }
                }
            }
        }

        // Contact name similarity
        if ($vendor1->contact_name && $vendor2->contact_name) {
            $contact1 = $this->normalizeString($vendor1->contact_name);
            $contact2 = $this->normalizeString($vendor2->contact_name);

            similar_text($contact1, $contact2, $contactSimilarity);
            if ($contactSimilarity > 70) {
                $scores[] = ($contactSimilarity / 100) * 0.1; // 10% weight
            }
        }

        return array_sum($scores);
    }

    /**
     * Get reasons why two vendors might be duplicates.
     */
    private function getMatchReasons(Vendor $vendor1, Vendor $vendor2): array
    {
        $reasons = [];

        // Company name
        if ($vendor1->company_name && $vendor2->company_name) {
            $name1 = $this->normalizeString($vendor1->company_name);
            $name2 = $this->normalizeString($vendor2->company_name);
            similar_text($name1, $name2, $similarity);
            if ($similarity > 60) {
                $reasons[] = 'Similar company names ('.round($similarity).'% match)';
            }
        }

        // Phone
        if ($vendor1->phone && $vendor2->phone) {
            $phone1 = preg_replace('/\D/', '', $vendor1->phone);
            $phone2 = preg_replace('/\D/', '', $vendor2->phone);
            if ($phone1 === $phone2 && strlen($phone1) >= 10) {
                $reasons[] = 'Same phone number';
            }
        }

        // Email
        if ($vendor1->email && $vendor2->email) {
            if (strtolower($vendor1->email) === strtolower($vendor2->email)) {
                $reasons[] = 'Same email address';
            }
        }

        // Contact name
        if ($vendor1->contact_name && $vendor2->contact_name) {
            $contact1 = $this->normalizeString($vendor1->contact_name);
            $contact2 = $this->normalizeString($vendor2->contact_name);
            similar_text($contact1, $contact2, $similarity);
            if ($similarity > 80) {
                $reasons[] = 'Similar contact names';
            }
        }

        return $reasons;
    }

    /**
     * Normalize a string for comparison.
     */
    private function normalizeString(string $str): string
    {
        $str = strtolower(trim($str));
        // Remove common suffixes
        $str = preg_replace('/\b(llc|inc|corp|co|ltd|company)\b/', '', $str);
        // Remove special characters
        $str = preg_replace('/[^a-z0-9\s]/', '', $str);
        // Collapse whitespace
        $str = preg_replace('/\s+/', ' ', $str);

        return trim($str);
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
