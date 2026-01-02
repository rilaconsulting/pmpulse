<?php

namespace Tests\Unit;

use App\Services\BusinessHoursService;
use Carbon\Carbon;
use Tests\TestCase;

class BusinessHoursServiceTest extends TestCase
{
    private BusinessHoursService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up default business hours config
        config([
            'appfolio.business_hours' => [
                'enabled' => true,
                'timezone' => 'America/Los_Angeles',
                'start_hour' => 9,
                'end_hour' => 17,
                'weekdays_only' => true,
                'business_hours_interval' => 15,
                'off_hours_interval' => 60,
            ],
        ]);

        $this->service = new BusinessHoursService;
    }

    public function test_is_business_hours_returns_true_during_weekday_business_hours(): void
    {
        // Tuesday at 10:30 AM Pacific
        Carbon::setTestNow(Carbon::create(2026, 1, 6, 10, 30, 0, 'America/Los_Angeles'));

        $this->assertTrue($this->service->isBusinessHours());
    }

    public function test_is_business_hours_returns_false_during_weekday_before_start(): void
    {
        // Tuesday at 8:30 AM Pacific (before 9 AM)
        Carbon::setTestNow(Carbon::create(2026, 1, 6, 8, 30, 0, 'America/Los_Angeles'));

        $this->assertFalse($this->service->isBusinessHours());
    }

    public function test_is_business_hours_returns_false_during_weekday_after_end(): void
    {
        // Tuesday at 6:30 PM Pacific (after 5 PM)
        Carbon::setTestNow(Carbon::create(2026, 1, 6, 18, 30, 0, 'America/Los_Angeles'));

        $this->assertFalse($this->service->isBusinessHours());
    }

    public function test_is_business_hours_returns_false_on_weekend(): void
    {
        // Saturday at 10:30 AM Pacific
        Carbon::setTestNow(Carbon::create(2026, 1, 3, 10, 30, 0, 'America/Los_Angeles'));

        $this->assertFalse($this->service->isBusinessHours());
    }

    public function test_is_business_hours_returns_true_on_weekend_when_weekdays_only_disabled(): void
    {
        config(['appfolio.business_hours.weekdays_only' => false]);
        $this->service = new BusinessHoursService;

        // Saturday at 10:30 AM Pacific
        Carbon::setTestNow(Carbon::create(2026, 1, 3, 10, 30, 0, 'America/Los_Angeles'));

        $this->assertTrue($this->service->isBusinessHours());
    }

    public function test_is_business_hours_returns_true_when_disabled(): void
    {
        config(['appfolio.business_hours.enabled' => false]);
        $this->service = new BusinessHoursService;

        // Saturday at 2:00 AM - should still return true when disabled
        Carbon::setTestNow(Carbon::create(2026, 1, 3, 2, 0, 0, 'America/Los_Angeles'));

        $this->assertTrue($this->service->isBusinessHours());
    }

    public function test_get_sync_interval_returns_business_hours_interval_during_business_hours(): void
    {
        // Tuesday at 10:30 AM Pacific
        Carbon::setTestNow(Carbon::create(2026, 1, 6, 10, 30, 0, 'America/Los_Angeles'));

        $this->assertEquals(15, $this->service->getSyncInterval());
    }

    public function test_get_sync_interval_returns_off_hours_interval_during_off_hours(): void
    {
        // Tuesday at 8:30 AM Pacific (before business hours)
        Carbon::setTestNow(Carbon::create(2026, 1, 6, 8, 30, 0, 'America/Los_Angeles'));

        $this->assertEquals(60, $this->service->getSyncInterval());
    }

    public function test_should_sync_now_returns_true_at_interval_boundary(): void
    {
        // Tuesday at 10:00 AM Pacific (00 min is divisible by 15)
        Carbon::setTestNow(Carbon::create(2026, 1, 6, 10, 0, 0, 'America/Los_Angeles'));

        $this->assertTrue($this->service->shouldSyncNow());
    }

    public function test_should_sync_now_returns_true_at_15_minute_interval(): void
    {
        // Tuesday at 10:15 AM Pacific
        Carbon::setTestNow(Carbon::create(2026, 1, 6, 10, 15, 0, 'America/Los_Angeles'));

        $this->assertTrue($this->service->shouldSyncNow());
    }

    public function test_should_sync_now_returns_false_between_intervals(): void
    {
        // Tuesday at 10:07 AM Pacific
        Carbon::setTestNow(Carbon::create(2026, 1, 6, 10, 7, 0, 'America/Los_Angeles'));

        $this->assertFalse($this->service->shouldSyncNow());
    }

    public function test_should_sync_now_returns_true_at_hourly_interval_during_off_hours(): void
    {
        // Tuesday at 7:00 AM Pacific (off-hours, 60 min interval)
        Carbon::setTestNow(Carbon::create(2026, 1, 6, 7, 0, 0, 'America/Los_Angeles'));

        $this->assertTrue($this->service->shouldSyncNow());
    }

    public function test_should_sync_now_returns_false_at_15_min_during_off_hours(): void
    {
        // Tuesday at 7:15 AM Pacific (off-hours, only 0 should sync)
        Carbon::setTestNow(Carbon::create(2026, 1, 6, 7, 15, 0, 'America/Los_Angeles'));

        $this->assertFalse($this->service->shouldSyncNow());
    }

    public function test_get_sync_mode_description_during_business_hours(): void
    {
        // Tuesday at 10:30 AM Pacific
        Carbon::setTestNow(Carbon::create(2026, 1, 6, 10, 30, 0, 'America/Los_Angeles'));

        $description = $this->service->getSyncModeDescription();

        $this->assertStringContainsString('Business hours mode', $description);
        $this->assertStringContainsString('15 minutes', $description);
    }

    public function test_get_sync_mode_description_during_off_hours(): void
    {
        // Tuesday at 8:30 AM Pacific
        Carbon::setTestNow(Carbon::create(2026, 1, 6, 8, 30, 0, 'America/Los_Angeles'));

        $description = $this->service->getSyncModeDescription();

        $this->assertStringContainsString('Off-hours mode', $description);
        $this->assertStringContainsString('60 minutes', $description);
    }

    public function test_get_configuration_returns_expected_structure(): void
    {
        // Tuesday at 10:30 AM Pacific
        Carbon::setTestNow(Carbon::create(2026, 1, 6, 10, 30, 0, 'America/Los_Angeles'));

        $config = $this->service->getConfiguration();

        $this->assertArrayHasKey('enabled', $config);
        $this->assertArrayHasKey('timezone', $config);
        $this->assertArrayHasKey('business_hours', $config);
        $this->assertArrayHasKey('weekdays_only', $config);
        $this->assertArrayHasKey('business_hours_interval', $config);
        $this->assertArrayHasKey('off_hours_interval', $config);
        $this->assertArrayHasKey('current_mode', $config);
        $this->assertArrayHasKey('current_interval', $config);
        $this->assertArrayHasKey('next_sync', $config);

        $this->assertTrue($config['enabled']);
        $this->assertEquals('America/Los_Angeles', $config['timezone']);
        $this->assertEquals('9:00 - 17:00', $config['business_hours']);
        $this->assertEquals('business_hours', $config['current_mode']);
        $this->assertEquals(15, $config['current_interval']);
    }

    public function test_get_next_sync_time_calculates_correctly(): void
    {
        // Tuesday at 10:07 AM Pacific - next sync at 10:15
        Carbon::setTestNow(Carbon::create(2026, 1, 6, 10, 7, 0, 'America/Los_Angeles'));

        $nextSync = $this->service->getNextSyncTime();

        $this->assertEquals(10, $nextSync->hour);
        $this->assertEquals(15, $nextSync->minute);
    }

    public function test_get_next_sync_time_at_boundary(): void
    {
        // Tuesday at 10:15 AM Pacific - currently at boundary
        Carbon::setTestNow(Carbon::create(2026, 1, 6, 10, 15, 0, 'America/Los_Angeles'));

        $nextSync = $this->service->getNextSyncTime();

        $this->assertEquals(10, $nextSync->hour);
        $this->assertEquals(15, $nextSync->minute);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // Reset Carbon
        parent::tearDown();
    }
}
