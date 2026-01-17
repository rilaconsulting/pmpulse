<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Jobs\GeocodePropertiesJob;
use App\Models\Property;
use App\Models\Setting;
use App\Services\GeocodingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class GeocodePropertiesJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        RateLimiter::clear('geocoding');

        // Configure geocoding service
        Setting::set('google', 'maps_api_key', 'test-api-key', encrypted: true);

        // Enable feature flag
        Setting::set('features', 'auto_geocoding', true);
    }

    public function test_geocodes_properties_without_coordinates(): void
    {
        // Create property without coordinates
        $property = Property::create([
            'external_id' => 'prop-123',
            'name' => 'Test Property',
            'address_line1' => '123 Main St',
            'city' => 'San Francisco',
            'state' => 'CA',
            'zip' => '94102',
            'is_active' => true,
        ]);

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

        $job = new GeocodePropertiesJob;
        $job->handle(app(GeocodingService::class));

        $property->refresh();

        $this->assertNotNull($property->latitude);
        $this->assertNotNull($property->longitude);
        $this->assertEqualsWithDelta(37.7749, (float) $property->latitude, 0.0001);
        $this->assertEqualsWithDelta(-122.4194, (float) $property->longitude, 0.0001);
    }

    public function test_skips_properties_with_coordinates(): void
    {
        // Create property WITH coordinates
        Property::create([
            'external_id' => 'prop-123',
            'name' => 'Already Geocoded',
            'address_line1' => '123 Main St',
            'city' => 'San Francisco',
            'state' => 'CA',
            'zip' => '94102',
            'latitude' => 37.0000,
            'longitude' => -122.0000,
            'is_active' => true,
        ]);

        Http::fake();

        $job = new GeocodePropertiesJob;
        $job->handle(app(GeocodingService::class));

        // Should not have made any API calls
        Http::assertNothingSent();
    }

    public function test_respects_feature_flag_when_disabled(): void
    {
        Setting::set('features', 'auto_geocoding', false);

        Property::create([
            'external_id' => 'prop-123',
            'name' => 'Test Property',
            'address_line1' => '123 Main St',
            'city' => 'San Francisco',
            'state' => 'CA',
            'zip' => '94102',
            'is_active' => true,
        ]);

        Http::fake();

        $job = new GeocodePropertiesJob;
        $job->handle(app(GeocodingService::class));

        // Should not have made any API calls
        Http::assertNothingSent();
    }

    public function test_skips_when_service_not_configured(): void
    {
        Setting::forget('google', 'maps_api_key');
        Cache::flush();

        Property::create([
            'external_id' => 'prop-123',
            'name' => 'Test Property',
            'address_line1' => '123 Main St',
            'city' => 'San Francisco',
            'state' => 'CA',
            'zip' => '94102',
            'is_active' => true,
        ]);

        Http::fake();

        $job = new GeocodePropertiesJob;
        $job->handle(app(GeocodingService::class));

        Http::assertNothingSent();
    }

    public function test_respects_batch_limit(): void
    {
        Queue::fake();

        // Create 3 properties
        for ($i = 1; $i <= 3; $i++) {
            Property::create([
                'external_id' => "prop-{$i}",
                'name' => "Test Property {$i}",
                'address_line1' => "{$i} Main St",
                'city' => 'San Francisco',
                'state' => 'CA',
                'zip' => '94102',
                'is_active' => true,
            ]);
        }

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

        // Limit to 2 properties
        $job = new GeocodePropertiesJob(limit: 2);
        $job->handle(app(GeocodingService::class));

        // Should have made only 2 API calls (follow-up job is queued, not executed)
        Http::assertSentCount(2);

        // Only 2 properties should have coordinates
        $geocoded = Property::query()->hasCoordinates()->count();
        $this->assertEquals(2, $geocoded);

        // Should dispatch follow-up job for remaining property
        Queue::assertPushed(GeocodePropertiesJob::class);
    }

    public function test_dispatches_follow_up_job_when_more_properties_remain(): void
    {
        Queue::fake();

        // Create more properties than batch size (default 25)
        for ($i = 1; $i <= 30; $i++) {
            Property::create([
                'external_id' => "prop-{$i}",
                'name' => "Test Property {$i}",
                'address_line1' => "{$i} Main St",
                'city' => 'San Francisco',
                'state' => 'CA',
                'zip' => '94102',
                'is_active' => true,
            ]);
        }

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

        // Use a small limit to trigger follow-up
        $job = new GeocodePropertiesJob(limit: 5);
        $job->handle(app(GeocodingService::class));

        // Should dispatch a follow-up job
        Queue::assertPushed(GeocodePropertiesJob::class);
    }

    public function test_skips_inactive_properties(): void
    {
        Property::create([
            'external_id' => 'prop-inactive',
            'name' => 'Inactive Property',
            'address_line1' => '123 Main St',
            'city' => 'San Francisco',
            'state' => 'CA',
            'zip' => '94102',
            'is_active' => false,
        ]);

        Http::fake();

        $job = new GeocodePropertiesJob;
        $job->handle(app(GeocodingService::class));

        Http::assertNothingSent();
    }

    public function test_handles_geocoding_failure_gracefully(): void
    {
        $property = Property::create([
            'external_id' => 'prop-123',
            'name' => 'Test Property',
            'address_line1' => '123 Main St',
            'city' => 'San Francisco',
            'state' => 'CA',
            'zip' => '94102',
            'is_active' => true,
        ]);

        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'ZERO_RESULTS',
                'results' => [],
            ], 200),
        ]);

        // Job should complete without throwing
        $job = new GeocodePropertiesJob;
        $job->handle(app(GeocodingService::class));

        $property->refresh();

        // Property should still not have coordinates
        $this->assertNull($property->latitude);
        $this->assertNull($property->longitude);
    }
}
