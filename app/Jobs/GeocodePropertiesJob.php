<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Property;
use App\Models\Setting;
use App\Services\GeocodingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to geocode properties that are missing coordinates.
 *
 * This job runs after property sync to fill in lat/lon for new properties.
 * It respects rate limits and can be disabled via feature flag.
 */
class GeocodePropertiesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600;

    /**
     * Maximum properties to geocode per job run.
     */
    private const BATCH_SIZE = 25;

    /**
     * Create a new job instance.
     *
     * @param  int|null  $limit  Maximum number of properties to process (null = BATCH_SIZE)
     */
    public function __construct(
        public readonly ?int $limit = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(GeocodingService $geocodingService): void
    {
        // Check feature flag
        if (! $this->isEnabled()) {
            Log::info('Property geocoding is disabled');

            return;
        }

        // Check if service is configured
        if (! $geocodingService->isConfigured()) {
            Log::warning('Geocoding service not configured, skipping property geocoding');

            return;
        }

        $limit = $this->limit ?? self::BATCH_SIZE;

        // Get properties that need geocoding
        $properties = Property::needsGeocoding()
            ->active()
            ->limit($limit)
            ->get();

        if ($properties->isEmpty()) {
            Log::info('No properties need geocoding');

            return;
        }

        Log::info('Starting property geocoding', [
            'count' => $properties->count(),
        ]);

        $geocoded = 0;
        $failed = 0;

        foreach ($properties as $property) {
            // Check rate limit before each geocode
            if ($geocodingService->getRemainingAttempts() <= 0) {
                Log::info('Geocoding rate limit reached, stopping batch');

                break;
            }

            $success = $this->geocodeProperty($property, $geocodingService);

            if ($success) {
                $geocoded++;
            } else {
                $failed++;
            }
        }

        Log::info('Property geocoding completed', [
            'geocoded' => $geocoded,
            'failed' => $failed,
            'remaining' => $properties->count() - $geocoded - $failed,
        ]);

        // If there are more properties to geocode, dispatch another job
        $remainingCount = Property::needsGeocoding()->active()->count();
        if ($remainingCount > 0 && $geocoded > 0) {
            Log::info('Dispatching follow-up geocoding job', [
                'remaining' => $remainingCount,
            ]);

            // Delay the next batch to allow rate limits to reset
            self::dispatch($this->limit)->delay(now()->addMinutes(2));
        }
    }

    /**
     * Geocode a single property.
     */
    private function geocodeProperty(Property $property, GeocodingService $geocodingService): bool
    {
        $address = $property->full_address;

        if (empty(trim($address))) {
            Log::warning('Property has no address to geocode', [
                'property_id' => $property->id,
            ]);

            return false;
        }

        $coordinates = $geocodingService->geocode($address);

        if ($coordinates === null) {
            Log::warning('Failed to geocode property', [
                'property_id' => $property->id,
                'address' => $address,
            ]);

            return false;
        }

        $property->update([
            'latitude' => $coordinates['latitude'],
            'longitude' => $coordinates['longitude'],
        ]);

        Log::debug('Property geocoded successfully', [
            'property_id' => $property->id,
            'latitude' => $coordinates['latitude'],
            'longitude' => $coordinates['longitude'],
        ]);

        return true;
    }

    /**
     * Check if auto-geocoding is enabled.
     */
    private function isEnabled(): bool
    {
        return Setting::isFeatureEnabled('auto_geocoding', true);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'geocoding',
            'properties',
        ];
    }
}
