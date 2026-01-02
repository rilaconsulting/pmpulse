<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class SettingModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_can_set_and_get_setting(): void
    {
        Setting::set('sync', 'batch_size', 100);

        $value = Setting::get('sync', 'batch_size');

        $this->assertEquals(100, $value);
    }

    public function test_get_returns_default_when_setting_not_found(): void
    {
        $value = Setting::get('sync', 'nonexistent', 'default_value');

        $this->assertEquals('default_value', $value);
    }

    public function test_can_store_array_values(): void
    {
        $resources = ['properties', 'units', 'leases'];
        Setting::set('sync', 'resources', $resources);

        $value = Setting::get('sync', 'resources');

        $this->assertEquals($resources, $value);
    }

    public function test_can_store_boolean_values(): void
    {
        Setting::set('features', 'notifications', true);

        $this->assertTrue(Setting::get('features', 'notifications'));
        $this->assertTrue(Setting::isFeatureEnabled('notifications'));
    }

    public function test_is_feature_enabled_returns_default_when_not_set(): void
    {
        $this->assertFalse(Setting::isFeatureEnabled('nonexistent'));
        $this->assertTrue(Setting::isFeatureEnabled('nonexistent', true));
    }

    public function test_can_store_encrypted_values(): void
    {
        $secret = 'super_secret_api_key';
        Setting::set('appfolio', 'client_secret', $secret, encrypted: true);

        // Verify it's encrypted in database
        $setting = Setting::where('category', 'appfolio')
            ->where('key', 'client_secret')
            ->first();

        $this->assertTrue($setting->encrypted);
        $this->assertNotEquals($secret, $setting->value);

        // Verify we can retrieve the decrypted value
        Cache::flush(); // Clear cache to force DB read
        $retrieved = Setting::get('appfolio', 'client_secret');
        $this->assertEquals($secret, $retrieved);
    }

    public function test_can_get_all_settings_in_category(): void
    {
        Setting::set('business_hours', 'enabled', true);
        Setting::set('business_hours', 'start_hour', 9);
        Setting::set('business_hours', 'end_hour', 17);
        Setting::set('business_hours', 'timezone', 'America/Los_Angeles');

        Cache::flush();
        $settings = Setting::getCategory('business_hours');

        $this->assertEquals([
            'enabled' => true,
            'start_hour' => 9,
            'end_hour' => 17,
            'timezone' => 'America/Los_Angeles',
        ], $settings);
    }

    public function test_set_updates_existing_setting(): void
    {
        Setting::set('sync', 'batch_size', 100);
        Setting::set('sync', 'batch_size', 200);

        $value = Setting::get('sync', 'batch_size');

        $this->assertEquals(200, $value);
        $this->assertEquals(1, Setting::where('category', 'sync')->where('key', 'batch_size')->count());
    }

    public function test_can_delete_setting(): void
    {
        Setting::set('sync', 'test_setting', 'test_value');
        $this->assertNotNull(Setting::get('sync', 'test_setting'));

        $deleted = Setting::forget('sync', 'test_setting');

        $this->assertTrue($deleted);
        Cache::flush();
        $this->assertNull(Setting::get('sync', 'test_setting'));
    }

    public function test_can_delete_entire_category(): void
    {
        Setting::set('temp', 'setting1', 'value1');
        Setting::set('temp', 'setting2', 'value2');
        Setting::set('temp', 'setting3', 'value3');

        $deleted = Setting::forgetCategory('temp');

        $this->assertEquals(3, $deleted);
        Cache::flush();
        $this->assertEmpty(Setting::getCategory('temp'));
    }

    public function test_caching_works(): void
    {
        Setting::set('cache_test', 'key', 'original_value');

        // First get should cache the value
        $value1 = Setting::get('cache_test', 'key');
        $this->assertEquals('original_value', $value1);

        // Directly update database (bypassing model)
        Setting::where('category', 'cache_test')
            ->where('key', 'key')
            ->update(['value' => json_encode('new_value')]);

        // Should still get cached value
        $value2 = Setting::get('cache_test', 'key');
        $this->assertEquals('original_value', $value2);

        // After cache clear, should get new value
        Cache::flush();
        $value3 = Setting::get('cache_test', 'key');
        $this->assertEquals('new_value', $value3);
    }

    public function test_set_invalidates_cache(): void
    {
        Setting::set('cache_test', 'key', 'original_value');
        $this->assertEquals('original_value', Setting::get('cache_test', 'key'));

        // Setting a new value should invalidate cache
        Setting::set('cache_test', 'key', 'updated_value');

        $this->assertEquals('updated_value', Setting::get('cache_test', 'key'));
    }

    public function test_unique_constraint_on_category_and_key(): void
    {
        Setting::set('unique_test', 'key', 'value1');

        // Using updateOrCreate, so this should update, not throw
        Setting::set('unique_test', 'key', 'value2');

        $this->assertEquals(1, Setting::where('category', 'unique_test')->count());
    }

    public function test_can_store_complex_nested_arrays(): void
    {
        $complexValue = [
            'schedule' => [
                'enabled' => true,
                'days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
                'hours' => ['start' => 9, 'end' => 17],
            ],
            'options' => [
                'retry' => 3,
                'timeout' => 30,
            ],
        ];

        Setting::set('sync', 'complex_config', $complexValue);
        Cache::flush();

        $retrieved = Setting::get('sync', 'complex_config');

        $this->assertEquals($complexValue, $retrieved);
    }

    public function test_can_store_null_values(): void
    {
        Setting::set('nullable', 'key', null);

        $value = Setting::get('nullable', 'key', 'default');

        $this->assertNull($value);
    }

    public function test_description_is_stored(): void
    {
        Setting::set(
            'documented',
            'setting',
            'value',
            encrypted: false,
            description: 'This is a test setting'
        );

        $setting = Setting::where('category', 'documented')
            ->where('key', 'setting')
            ->first();

        $this->assertEquals('This is a test setting', $setting->description);
    }
}
