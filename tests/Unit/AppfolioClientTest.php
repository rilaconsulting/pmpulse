<?php

namespace Tests\Unit;

use App\Models\AppfolioConnection;
use App\Services\AppfolioClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AppfolioClientTest extends TestCase
{
    use RefreshDatabase;

    private AppfolioClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a configured connection
        AppfolioConnection::create([
            'name' => 'Test Connection',
            'client_id' => 'test-client-id',
            'client_secret_encrypted' => encrypt('test-client-secret'),
            'api_base_url' => 'https://api.appfolio.test',
            'status' => 'configured',
        ]);

        $this->client = new AppfolioClient();
    }

    public function test_is_configured_returns_true_when_credentials_are_set(): void
    {
        $this->assertTrue($this->client->isConfigured());
    }

    public function test_is_configured_returns_false_when_no_connection(): void
    {
        AppfolioConnection::truncate();
        $client = new AppfolioClient();

        $this->assertFalse($client->isConfigured());
    }

    public function test_get_properties_returns_array_on_success(): void
    {
        Http::fake([
            'api.appfolio.test/*' => Http::response([
                'data' => [
                    ['id' => '1', 'name' => 'Property 1'],
                    ['id' => '2', 'name' => 'Property 2'],
                ],
            ], 200),
        ]);

        $result = $this->client->getProperties();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertCount(2, $result['data']);
    }

    public function test_get_properties_includes_pagination_params(): void
    {
        Http::fake([
            'api.appfolio.test/*' => Http::response(['data' => []], 200),
        ]);

        $this->client->getProperties(['page' => 2, 'per_page' => 50]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'page=2')
                && str_contains($request->url(), 'per_page=50');
        });
    }

    public function test_handles_rate_limit_with_retry(): void
    {
        $callCount = 0;

        Http::fake(function () use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                return Http::response('Rate limited', 429, ['Retry-After' => '1']);
            }
            return Http::response(['data' => []], 200);
        });

        $result = $this->client->getProperties();

        $this->assertIsArray($result);
        $this->assertEquals(2, $callCount);
    }

    public function test_handles_server_error_with_retry(): void
    {
        $callCount = 0;

        Http::fake(function () use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                return Http::response('Server error', 500);
            }
            return Http::response(['data' => []], 200);
        });

        $result = $this->client->getProperties();

        $this->assertIsArray($result);
        $this->assertEquals(2, $callCount);
    }

    public function test_throws_exception_on_client_error(): void
    {
        Http::fake([
            'api.appfolio.test/*' => Http::response('Not found', 404),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('AppFolio API error: 404');

        $this->client->getProperties();
    }

    public function test_get_units_calls_correct_endpoint(): void
    {
        Http::fake([
            'api.appfolio.test/v1/units*' => Http::response(['data' => []], 200),
        ]);

        $this->client->getUnits();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/v1/units');
        });
    }

    public function test_get_work_orders_calls_correct_endpoint(): void
    {
        Http::fake([
            'api.appfolio.test/v1/work-orders*' => Http::response(['data' => []], 200),
        ]);

        $this->client->getWorkOrders();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/v1/work-orders');
        });
    }

    public function test_test_connection_returns_true_on_success(): void
    {
        Http::fake([
            'api.appfolio.test/*' => Http::response(['data' => []], 200),
        ]);

        $result = $this->client->testConnection();

        $this->assertTrue($result);
    }

    public function test_test_connection_returns_false_on_failure(): void
    {
        Http::fake([
            'api.appfolio.test/*' => Http::response('Error', 500),
        ]);

        // Override max retries for faster test
        config(['appfolio.rate_limit.max_retries' => 1]);

        $result = $this->client->testConnection();

        $this->assertFalse($result);
    }
}
