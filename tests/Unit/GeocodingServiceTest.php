<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\GeocodingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class GeocodingServiceTest extends TestCase
{
    use RefreshDatabase;

    private GeocodingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        RateLimiter::clear('geocoding');

        // Configure API key via Settings
        Setting::set('google', 'maps_api_key', 'test-api-key', encrypted: true);

        $this->service = new GeocodingService;
    }

    public function test_geocode_returns_coordinates_for_valid_address(): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'results' => [
                    [
                        'geometry' => [
                            'location' => [
                                'lat' => 37.7749,
                                'lng' => -122.4194,
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->service->geocode('123 Main St, San Francisco, CA 94102');

        $this->assertNotNull($result);
        $this->assertEqualsWithDelta(37.7749, $result['latitude'], 0.0001);
        $this->assertEqualsWithDelta(-122.4194, $result['longitude'], 0.0001);
    }

    public function test_geocode_returns_null_for_empty_address(): void
    {
        $result = $this->service->geocode('');

        $this->assertNull($result);
    }

    public function test_geocode_returns_null_for_zero_results(): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'ZERO_RESULTS',
                'results' => [],
            ], 200),
        ]);

        $result = $this->service->geocode('Invalid Address XYZ123');

        $this->assertNull($result);
    }

    public function test_geocode_returns_null_for_api_error(): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'REQUEST_DENIED',
                'error_message' => 'Invalid API key',
            ], 200),
        ]);

        $result = $this->service->geocode('123 Main St');

        $this->assertNull($result);
    }

    public function test_geocode_caches_successful_results(): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'results' => [
                    [
                        'geometry' => [
                            'location' => [
                                'lat' => 37.7749,
                                'lng' => -122.4194,
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $address = '123 Main St, San Francisco, CA';

        // First call - should hit API
        $result1 = $this->service->geocode($address);
        $this->assertNotNull($result1);

        // Second call - should use cache
        $result2 = $this->service->geocode($address);
        $this->assertNotNull($result2);

        // Verify only one API call was made
        Http::assertSentCount(1);
    }

    public function test_geocode_returns_null_when_rate_limited(): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'results' => [
                    [
                        'geometry' => [
                            'location' => [
                                'lat' => 37.7749,
                                'lng' => -122.4194,
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        // Exhaust rate limit
        for ($i = 0; $i < 30; $i++) {
            RateLimiter::hit('geocoding', 60);
        }

        $result = $this->service->geocode('123 Main St');

        $this->assertNull($result);
        // No API call should be made when rate limited
        Http::assertNothingSent();
    }

    public function test_is_configured_returns_false_when_no_api_key(): void
    {
        Setting::forget('google', 'maps_api_key');
        Cache::flush();

        $service = new GeocodingService;

        $this->assertFalse($service->isConfigured());
    }

    public function test_is_configured_returns_true_when_api_key_set(): void
    {
        $this->assertTrue($this->service->isConfigured());
    }

    public function test_geocode_returns_null_when_not_configured(): void
    {
        Setting::forget('google', 'maps_api_key');
        Cache::flush();

        $service = new GeocodingService;

        Http::fake();

        $result = $service->geocode('123 Main St, San Francisco, CA');

        $this->assertNull($result);
        // Verify no API call was made
        Http::assertNothingSent();
    }

    public function test_geocode_handles_http_failure(): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response(null, 500),
        ]);

        $result = $this->service->geocode('123 Main St');

        $this->assertNull($result);
    }

    public function test_clear_cache_removes_cached_result(): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'results' => [
                    [
                        'geometry' => [
                            'location' => [
                                'lat' => 37.7749,
                                'lng' => -122.4194,
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $address = '123 Main St';

        // Cache a result
        $this->service->geocode($address);

        // Clear the cache
        $this->service->clearCache($address);

        // Next call should hit API again
        $this->service->geocode($address);

        Http::assertSentCount(2);
    }

    public function test_get_remaining_attempts_returns_correct_count(): void
    {
        $initial = $this->service->getRemainingAttempts();

        $this->assertEquals(30, $initial);

        // Use some attempts
        RateLimiter::hit('geocoding', 60);
        RateLimiter::hit('geocoding', 60);

        $remaining = $this->service->getRemainingAttempts();

        $this->assertEquals(28, $remaining);
    }
}
