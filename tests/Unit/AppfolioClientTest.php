<?php

namespace Tests\Unit;

use App\Models\Setting;
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

        // Create configured AppFolio settings
        Setting::set('appfolio', 'client_id', 'test-client-id');
        Setting::set('appfolio', 'client_secret', 'test-client-secret', encrypted: true);
        Setting::set('appfolio', 'database', 'testdb');
        Setting::set('appfolio', 'status', 'configured');

        $this->client = new AppfolioClient;
    }

    public function test_is_configured_returns_true_when_credentials_are_set(): void
    {
        $this->assertTrue($this->client->isConfigured());
    }

    public function test_is_configured_returns_false_when_no_connection(): void
    {
        Setting::forgetCategory('appfolio');
        $client = new AppfolioClient;

        $this->assertFalse($client->isConfigured());
    }

    public function test_handles_rate_limit_with_retry(): void
    {
        $callCount = 0;

        Http::fake(function () use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                return Http::response('Rate limited', 429, ['Retry-After' => '1']);
            }

            return Http::response(['results' => []], 200);
        });

        $result = $this->client->getPropertyDirectory();

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

            return Http::response(['results' => []], 200);
        });

        $result = $this->client->getPropertyDirectory();

        $this->assertIsArray($result);
        $this->assertEquals(2, $callCount);
    }

    public function test_throws_exception_on_client_error(): void
    {
        Http::fake([
            'https://testdb.appfolio.com/*' => Http::response('Not found', 404),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('AppFolio API error: 404');

        $this->client->getPropertyDirectory();
    }

    public function test_test_connection_returns_true_on_success(): void
    {
        Http::fake([
            'https://testdb.appfolio.com/*' => Http::response(['results' => []], 200),
        ]);

        $result = $this->client->testConnection();

        $this->assertTrue($result);
    }

    public function test_test_connection_returns_false_on_failure(): void
    {
        Http::fake([
            'https://testdb.appfolio.com/*' => Http::response('Error', 500),
        ]);

        // Override max retries for faster test
        config(['appfolio.rate_limit.max_retries' => 1]);

        $result = $this->client->testConnection();

        $this->assertFalse($result);
    }

    // =========================================================================
    // Reports API Tests
    // =========================================================================

    public function test_get_property_directory_calls_correct_endpoint(): void
    {
        Http::fake([
            'https://testdb.appfolio.com/api/v2/reports/property_directory.json' => Http::response([
                'results' => [
                    ['property_id' => 1, 'property_name' => 'Test Property'],
                ],
            ], 200),
        ]);

        $result = $this->client->getPropertyDirectory();

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/api/v2/reports/property_directory.json');
        });

        $this->assertIsArray($result);
        $this->assertArrayHasKey('results', $result);
    }

    public function test_get_unit_directory_calls_correct_endpoint(): void
    {
        Http::fake([
            'https://testdb.appfolio.com/api/v2/reports/unit_directory.json' => Http::response([
                'results' => [
                    ['unit_id' => 1, 'unit_name' => '101'],
                ],
            ], 200),
        ]);

        $result = $this->client->getUnitDirectory();

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/api/v2/reports/unit_directory.json');
        });

        $this->assertIsArray($result);
    }

    public function test_get_vendor_directory_calls_correct_endpoint(): void
    {
        Http::fake([
            'https://testdb.appfolio.com/api/v2/reports/vendor_directory.json' => Http::response([
                'results' => [
                    ['vendor_id' => 1, 'company_name' => 'Test Vendor'],
                ],
            ], 200),
        ]);

        $result = $this->client->getVendorDirectory();

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/api/v2/reports/vendor_directory.json');
        });

        $this->assertIsArray($result);
    }

    public function test_get_expense_register_calls_correct_endpoint(): void
    {
        Http::fake([
            'https://testdb.appfolio.com/api/v2/reports/expense_register.json' => Http::response([
                'results' => [
                    ['expense_id' => 1, 'amount' => '100.00'],
                ],
            ], 200),
        ]);

        $result = $this->client->getExpenseRegister([
            'from_date' => '2025-01-01',
            'to_date' => '2025-12-31',
        ]);

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/api/v2/reports/expense_register.json');
        });

        $this->assertIsArray($result);
    }

    public function test_get_work_order_report_calls_correct_endpoint(): void
    {
        Http::fake([
            'https://testdb.appfolio.com/api/v2/reports/work_order.json' => Http::response([
                'results' => [
                    ['work_order_id' => 1, 'status' => 'open'],
                ],
            ], 200),
        ]);

        $result = $this->client->getWorkOrderReport([
            'from_date' => '2025-01-01',
            'to_date' => '2025-12-31',
        ]);

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/api/v2/reports/work_order.json');
        });

        $this->assertIsArray($result);
    }

    public function test_get_rent_roll_calls_correct_endpoint(): void
    {
        Http::fake([
            'https://testdb.appfolio.com/api/v2/reports/rent_roll.json' => Http::response([
                'results' => [],
            ], 200),
        ]);

        $result = $this->client->getRentRoll();

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/api/v2/reports/rent_roll.json');
        });

        $this->assertIsArray($result);
    }

    public function test_get_delinquency_calls_correct_endpoint(): void
    {
        Http::fake([
            'https://testdb.appfolio.com/api/v2/reports/delinquency.json' => Http::response([
                'results' => [],
            ], 200),
        ]);

        $result = $this->client->getDelinquency();

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/api/v2/reports/delinquency.json');
        });

        $this->assertIsArray($result);
    }

    public function test_fetch_all_pages_handles_pagination(): void
    {
        $callCount = 0;

        Http::fake(function ($request) use (&$callCount) {
            $callCount++;

            if ($callCount === 1) {
                return Http::response([
                    'results' => [['id' => 1], ['id' => 2]],
                    'next_page_url' => '/api/v2/reports/property_directory.json?page=2',
                ], 200);
            }

            return Http::response([
                'results' => [['id' => 3], ['id' => 4]],
                'next_page_url' => null,
            ], 200);
        });

        $results = $this->client->fetchAllPages('getPropertyDirectory');

        $this->assertCount(4, $results);
        $this->assertEquals(2, $callCount);
    }

    public function test_fetch_all_pages_calls_progress_callback(): void
    {
        $callCount = 0;
        $progressCalls = [];

        Http::fake(function ($request) use (&$callCount) {
            $callCount++;

            if ($callCount === 1) {
                return Http::response([
                    'results' => [['id' => 1], ['id' => 2]],
                    'next_page_url' => '/api/v2/reports/property_directory.json?page=2',
                ], 200);
            }

            return Http::response([
                'results' => [['id' => 3]],
                'next_page_url' => null,
            ], 200);
        });

        $results = $this->client->fetchAllPages(
            'getPropertyDirectory',
            [],
            function ($page, $recordsFetched, $hasMore) use (&$progressCalls) {
                $progressCalls[] = [
                    'page' => $page,
                    'records' => $recordsFetched,
                    'hasMore' => $hasMore,
                ];
            }
        );

        $this->assertCount(3, $results);
        $this->assertCount(2, $progressCalls);
        $this->assertEquals(1, $progressCalls[0]['page']);
        $this->assertEquals(2, $progressCalls[0]['records']);
        $this->assertTrue($progressCalls[0]['hasMore']);
        $this->assertEquals(2, $progressCalls[1]['page']);
        $this->assertEquals(3, $progressCalls[1]['records']);
        $this->assertFalse($progressCalls[1]['hasMore']);
    }

    public function test_fetch_all_pages_respects_max_pages_limit(): void
    {
        $callCount = 0;

        Http::fake(function ($request) use (&$callCount) {
            $callCount++;

            // Always return more pages
            return Http::response([
                'results' => [['id' => $callCount]],
                'next_page_url' => '/api/v2/reports/property_directory.json?page='.($callCount + 1),
            ], 200);
        });

        $results = $this->client->fetchAllPages('getPropertyDirectory', [], null, 3);

        $this->assertCount(3, $results);
        $this->assertEquals(3, $callCount);
    }

    public function test_fetch_all_pages_returns_partial_results_on_error(): void
    {
        $callCount = 0;

        Http::fake(function ($request) use (&$callCount) {
            $callCount++;

            if ($callCount === 1) {
                return Http::response([
                    'results' => [['id' => 1], ['id' => 2]],
                    'next_page_url' => '/api/v2/reports/property_directory.json?page=2',
                ], 200);
            }

            // Second page fails
            return Http::response('Server error', 500);
        });

        // Override max retries for faster test
        config(['appfolio.rate_limit.max_retries' => 0]);

        $results = $this->client->fetchAllPages('getPropertyDirectory');

        // Should return partial results from first page
        $this->assertCount(2, $results);
    }

    public function test_report_endpoints_include_default_pagination_params(): void
    {
        Http::fake([
            'https://testdb.appfolio.com/*' => Http::response(['results' => []], 200),
        ]);

        $this->client->getPropertyDirectory();

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return $body['paginate_results'] === true
                && isset($body['per_page']);
        });
    }

    public function test_get_pagination_info_returns_expected_structure(): void
    {
        Http::fake([
            'https://testdb.appfolio.com/*' => Http::response([
                'results' => [['id' => 1]],
                'next_page_url' => '/api/v2/reports/property_directory.json?page=2',
            ], 200),
        ]);

        $info = $this->client->getPaginationInfo('getPropertyDirectory');

        $this->assertArrayHasKey('has_results', $info);
        $this->assertArrayHasKey('has_more_pages', $info);
        $this->assertArrayHasKey('per_page', $info);
        $this->assertTrue($info['has_results']);
        $this->assertTrue($info['has_more_pages']);
    }
}
