<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Psr\Log\LoggerInterface;

/**
 * Geocoding Service
 *
 * Geocodes addresses using the Google Geocoding API.
 * Includes caching, rate limiting, and error handling.
 */
class GeocodingService
{
    public function __construct(
        private readonly CacheRepository $cache,
        private readonly HttpFactory $http,
        private readonly LoggerInterface $log,
        private readonly RateLimiter $rateLimiter,
    ) {}

    /**
     * Google Geocoding API base URL.
     */
    private const GOOGLE_API_URL = 'https://maps.googleapis.com/maps/api/geocode/json';

    /**
     * Cache TTL in seconds (7 days).
     */
    private const CACHE_TTL = 604800;

    /**
     * Rate limiter key prefix.
     */
    private const RATE_LIMIT_KEY = 'geocoding';

    /**
     * Maximum requests per minute (Google's free tier allows 50/second, we're conservative).
     */
    private const RATE_LIMIT_PER_MINUTE = 30;

    /**
     * Geocode an address and return coordinates.
     *
     * @param  string  $address  The full address to geocode
     * @return array{latitude: float, longitude: float}|null Coordinates or null on failure
     */
    public function geocode(string $address): ?array
    {
        $address = trim($address);

        if (empty($address)) {
            return null;
        }

        // Check cache first
        $cacheKey = $this->getCacheKey($address);
        $cached = $this->cache->get($cacheKey);

        if ($cached !== null) {
            $this->log->debug('Geocoding cache hit', ['address' => $address]);

            return $cached;
        }

        // Check rate limit
        if (! $this->checkRateLimit()) {
            $this->log->warning('Geocoding rate limit exceeded');

            return null;
        }

        // Make API request
        $result = $this->makeRequest($address);

        if ($result !== null) {
            // Cache the result
            $this->cache->put($cacheKey, $result, self::CACHE_TTL);
        }

        return $result;
    }

    /**
     * Check if the service is configured.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->getApiKey());
    }

    /**
     * Get the API key from settings.
     */
    private function getApiKey(): ?string
    {
        return Setting::get('google', 'maps_api_key');
    }

    /**
     * Check and consume rate limit.
     */
    private function checkRateLimit(): bool
    {
        $key = self::RATE_LIMIT_KEY;

        if ($this->rateLimiter->tooManyAttempts($key, self::RATE_LIMIT_PER_MINUTE)) {
            return false;
        }

        $this->rateLimiter->hit($key, 60);

        return true;
    }

    /**
     * Make the actual API request to Google Maps Geocoding API.
     */
    private function makeRequest(string $address): ?array
    {
        $apiKey = $this->getApiKey();

        if (empty($apiKey)) {
            $this->log->warning('Google Maps API key not configured for geocoding');

            return null;
        }

        return $this->makeGoogleRequest($address, $apiKey);
    }

    /**
     * Make a request to Google Geocoding API.
     */
    private function makeGoogleRequest(string $address, string $apiKey): ?array
    {
        try {
            $response = $this->http->timeout(10)
                ->get(self::GOOGLE_API_URL, [
                    'address' => $address,
                    'key' => $apiKey,
                ]);

            if (! $response->successful()) {
                $this->log->error('Google Geocoding API request failed', [
                    'status' => $response->status(),
                    'address' => $address,
                ]);

                return null;
            }

            $data = $response->json();

            return $this->parseGoogleResponse($data, $address);
        } catch (\Exception $e) {
            $this->log->error('Google Geocoding API exception', [
                'error' => $e->getMessage(),
                'address' => $address,
            ]);

            return null;
        }
    }

    /**
     * Parse the Google API response.
     */
    private function parseGoogleResponse(array $data, string $address): ?array
    {
        $status = $data['status'] ?? 'UNKNOWN_ERROR';

        if ($status !== 'OK') {
            $this->handleApiError($status, $address);

            return null;
        }

        $results = $data['results'] ?? [];

        if (empty($results)) {
            $this->log->warning('Google Geocoding returned no results', ['address' => $address]);

            return null;
        }

        $location = $results[0]['geometry']['location'] ?? null;

        if (! $location || ! isset($location['lat'], $location['lng'])) {
            $this->log->warning('Google Geocoding result missing location data', ['address' => $address]);

            return null;
        }

        return [
            'latitude' => (float) $location['lat'],
            'longitude' => (float) $location['lng'],
        ];
    }

    /**
     * Handle API error status codes.
     */
    private function handleApiError(string $status, string $address): void
    {
        $message = match ($status) {
            'ZERO_RESULTS' => 'No results found for address',
            'OVER_QUERY_LIMIT' => 'API quota exceeded',
            'REQUEST_DENIED' => 'API request denied (check API key)',
            'INVALID_REQUEST' => 'Invalid request (check address format)',
            default => "API error: {$status}",
        };

        $this->log->warning("Geocoding error: {$message}", [
            'status' => $status,
            'address' => $address,
        ]);
    }

    /**
     * Generate a cache key for an address.
     */
    private function getCacheKey(string $address): string
    {
        return 'geocode:'.md5(strtolower($address));
    }

    /**
     * Clear the cache for a specific address.
     */
    public function clearCache(string $address): void
    {
        $this->cache->forget($this->getCacheKey($address));
    }

    /**
     * Get remaining rate limit attempts.
     */
    public function getRemainingAttempts(): int
    {
        return $this->rateLimiter->remaining(self::RATE_LIMIT_KEY, self::RATE_LIMIT_PER_MINUTE);
    }
}
