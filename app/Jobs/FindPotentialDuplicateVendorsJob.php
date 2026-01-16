<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Vendor;
use App\Models\VendorDuplicateAnalysis;
use App\Services\VendorDeduplicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FindPotentialDuplicateVendorsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300; // 5 minutes

    public function __construct(
        public VendorDuplicateAnalysis $analysis
    ) {}

    public function handle(VendorDeduplicationService $deduplicationService): void
    {
        Log::info("Starting vendor duplicate analysis: {$this->analysis->id}");

        $this->analysis->markAsProcessing();

        try {
            // Count total vendors to track comparisons
            $totalVendors = Vendor::canonical()->count();
            $comparisons = ($totalVendors * ($totalVendors - 1)) / 2;

            // Find potential duplicates
            $results = $deduplicationService->findPotentialDuplicates(
                threshold: $this->analysis->threshold,
                limit: $this->analysis->limit
            );

            // Transform results for storage
            $formattedResults = array_map(function ($pair) {
                return [
                    'vendor1' => [
                        'id' => $pair['vendor1']->id,
                        'company_name' => $pair['vendor1']->company_name,
                        'contact_name' => $pair['vendor1']->contact_name,
                        'email' => $pair['vendor1']->email,
                        'phone' => $pair['vendor1']->phone,
                        'vendor_trades' => $pair['vendor1']->vendor_trades,
                    ],
                    'vendor2' => [
                        'id' => $pair['vendor2']->id,
                        'company_name' => $pair['vendor2']->company_name,
                        'contact_name' => $pair['vendor2']->contact_name,
                        'email' => $pair['vendor2']->email,
                        'phone' => $pair['vendor2']->phone,
                        'vendor_trades' => $pair['vendor2']->vendor_trades,
                    ],
                    'similarity' => $pair['similarity'],
                    'match_reasons' => $pair['match_reasons'],
                ];
            }, $results);

            $this->analysis->markAsCompleted(
                results: $formattedResults,
                totalVendors: $totalVendors,
                comparisons: (int) $comparisons,
                duplicatesFound: count($formattedResults)
            );

            Log::info("Vendor duplicate analysis completed: {$this->analysis->id}, found {$this->analysis->duplicates_found} potential duplicates");
        } catch (\Throwable $e) {
            Log::error("Vendor duplicate analysis failed: {$this->analysis->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->analysis->markAsFailed($e->getMessage());

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Vendor duplicate analysis job failed: {$this->analysis->id}", [
            'error' => $exception->getMessage(),
        ]);

        $this->analysis->markAsFailed($exception->getMessage());
    }
}
