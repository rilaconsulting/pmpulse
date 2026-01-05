<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AppFolio API Client
 *
 * This service handles all communication with the AppFolio API.
 * It includes rate limiting, exponential backoff, and retry logic.
 *
 * The AppFolio Reports API V2 is accessed at:
 * https://{database}.appfolio.com/api/v2/reports/{endpoint}.json
 *
 * Authentication uses HTTP Basic Auth with Client ID and Client Secret.
 */
class AppfolioClient
{
    private int $retryCount = 0;

    /**
     * Cached connection settings.
     */
    private ?array $connectionSettings = null;

    /**
     * AppFolio Reports API V2 endpoints.
     * All endpoints use POST with JSON body parameters.
     * Full URL format: https://{database}.appfolio.com/api/v2/reports/{endpoint}.json
     */
    private const REPORT_ENDPOINTS = [
        'property_directory' => '/api/v2/reports/property_directory.json',
        'unit_directory' => '/api/v2/reports/unit_directory.json',
        'vendor_directory' => '/api/v2/reports/vendor_directory.json',
        'work_order' => '/api/v2/reports/work_order.json',
        'expense_register' => '/api/v2/reports/expense_register.json',
        'rent_roll' => '/api/v2/reports/rent_roll.json',
        'delinquency' => '/api/v2/reports/delinquency.json',
    ];

    /**
     * Get the connection settings from the database.
     */
    private function getConnectionSettings(): array
    {
        if ($this->connectionSettings === null) {
            $this->connectionSettings = Setting::getCategory('appfolio');
        }

        return $this->connectionSettings;
    }

    /**
     * Check if the client is properly configured.
     */
    public function isConfigured(): bool
    {
        $settings = $this->getConnectionSettings();

        return ! empty($settings['client_id'])
            && ! empty($settings['client_secret'])
            && ! empty($settings['database']);
    }

    /**
     * Get the database name (vhost).
     *
     * This is the subdomain used in the AppFolio URL.
     * For example, if the URL is https://sutro.appfolio.com, the database is "sutro".
     */
    public function getDatabase(): ?string
    {
        return $this->getConnectionSettings()['database'] ?? null;
    }

    /**
     * Get the API base URL.
     *
     * Constructs the full base URL from the database name.
     * Format: https://{database}.appfolio.com
     */
    public function getApiBaseUrl(): string
    {
        $database = $this->getDatabase();

        if (empty($database)) {
            throw new \RuntimeException('AppFolio database name is not configured');
        }

        return "https://{$database}.appfolio.com";
    }

    /**
     * Get the client ID.
     */
    public function getClientId(): ?string
    {
        return $this->getConnectionSettings()['client_id'] ?? null;
    }

    /**
     * Get the connection status.
     */
    public function getStatus(): string
    {
        return $this->getConnectionSettings()['status'] ?? 'not_configured';
    }

    /**
     * Get the last success timestamp.
     */
    public function getLastSuccessAt(): ?string
    {
        return $this->getConnectionSettings()['last_success_at'] ?? null;
    }

    /**
     * Get the last error message.
     */
    public function getLastError(): ?string
    {
        return $this->getConnectionSettings()['last_error'] ?? null;
    }

    /**
     * Mark the connection as successfully synced.
     */
    public function markAsSuccess(): void
    {
        Setting::set('appfolio', 'status', 'connected');
        Setting::set('appfolio', 'last_success_at', now()->toIso8601String());
        Setting::set('appfolio', 'last_error', null);
        $this->connectionSettings = null; // Clear cache
    }

    /**
     * Mark the connection as having an error.
     */
    public function markAsError(string $error): void
    {
        Setting::set('appfolio', 'status', 'error');
        Setting::set('appfolio', 'last_error', $error);
        $this->connectionSettings = null; // Clear cache
    }

    /**
     * Get the base HTTP client with authentication.
     *
     * Uses HTTP Basic Auth as per AppFolio API documentation.
     */
    private function getHttpClient(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('AppFolio connection is not configured');
        }

        $settings = $this->getConnectionSettings();
        $baseUrl = $this->getApiBaseUrl();
        $clientId = $settings['client_id'];
        $clientSecret = $settings['client_secret'];

        return Http::baseUrl($baseUrl)
            ->withBasicAuth($clientId, $clientSecret)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'User-Agent' => 'PMPulse/1.0',
            ])
            ->timeout(30);
    }

    /**
     * Make an API request with retry logic and exponential backoff.
     *
     * @param  string  $method  HTTP method
     * @param  string  $endpoint  API endpoint
     * @param  array  $params  Query parameters
     * @return array|null Response data
     *
     * @throws \Exception On permanent failure
     */
    private function request(string $method, string $endpoint, array $params = []): ?array
    {
        $config = config('appfolio.rate_limit');
        $maxRetries = $config['max_retries'];
        $initialBackoff = $config['initial_backoff_seconds'];
        $backoffMultiplier = $config['backoff_multiplier'];
        $maxBackoff = $config['max_backoff_seconds'];

        $this->retryCount = 0;

        while ($this->retryCount <= $maxRetries) {
            try {
                $response = $this->makeRequest($method, $endpoint, $params);

                if ($response->successful()) {
                    return $response->json();
                }

                // Handle rate limiting (429)
                if ($response->status() === 429) {
                    $this->handleRateLimit($response, $initialBackoff, $backoffMultiplier, $maxBackoff);

                    continue;
                }

                // Handle server errors (5xx) - retry
                if ($response->serverError()) {
                    $this->handleServerError($response, $initialBackoff, $backoffMultiplier, $maxBackoff);

                    continue;
                }

                // Client errors (4xx except 429) - don't retry
                if ($response->clientError()) {
                    Log::error('AppFolio API client error', [
                        'endpoint' => $endpoint,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    throw new \Exception("AppFolio API error: {$response->status()} - {$response->body()}");
                }

            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $this->handleConnectionError($e, $initialBackoff, $backoffMultiplier, $maxBackoff);
            }
        }

        throw new \Exception("AppFolio API request failed after {$maxRetries} retries");
    }

    /**
     * Make the actual HTTP request.
     */
    private function makeRequest(string $method, string $endpoint, array $params): Response
    {
        $client = $this->getHttpClient();

        return match (strtoupper($method)) {
            'GET' => $client->get($endpoint, $params),
            'POST' => $client->post($endpoint, $params),
            'PUT' => $client->put($endpoint, $params),
            'PATCH' => $client->patch($endpoint, $params),
            'DELETE' => $client->delete($endpoint, $params),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };
    }

    /**
     * Handle rate limit response.
     */
    private function handleRateLimit(Response $response, int $initialBackoff, float $multiplier, int $maxBackoff): void
    {
        $this->retryCount++;

        // Check for Retry-After header
        $retryAfter = $response->header('Retry-After');
        if ($retryAfter) {
            $sleepSeconds = min((int) $retryAfter, $maxBackoff);
        } else {
            $sleepSeconds = min($initialBackoff * pow($multiplier, $this->retryCount - 1), $maxBackoff);
        }

        Log::warning('AppFolio API rate limited', [
            'retry_count' => $this->retryCount,
            'sleep_seconds' => $sleepSeconds,
        ]);

        sleep((int) $sleepSeconds);
    }

    /**
     * Handle server error response.
     */
    private function handleServerError(Response $response, int $initialBackoff, float $multiplier, int $maxBackoff): void
    {
        $this->retryCount++;
        $sleepSeconds = min($initialBackoff * pow($multiplier, $this->retryCount - 1), $maxBackoff);

        Log::warning('AppFolio API server error', [
            'status' => $response->status(),
            'retry_count' => $this->retryCount,
            'sleep_seconds' => $sleepSeconds,
        ]);

        sleep((int) $sleepSeconds);
    }

    /**
     * Handle connection error.
     */
    private function handleConnectionError(\Exception $e, int $initialBackoff, float $multiplier, int $maxBackoff): void
    {
        $this->retryCount++;
        $maxRetries = config('appfolio.rate_limit.max_retries');

        if ($this->retryCount > $maxRetries) {
            throw $e;
        }

        $sleepSeconds = min($initialBackoff * pow($multiplier, $this->retryCount - 1), $maxBackoff);

        Log::warning('AppFolio API connection error', [
            'error' => $e->getMessage(),
            'retry_count' => $this->retryCount,
            'sleep_seconds' => $sleepSeconds,
        ]);

        sleep((int) $sleepSeconds);
    }

    /**
     * Test the connection to AppFolio API.
     *
     * @return bool True if connection is successful
     */
    public function testConnection(): bool
    {
        try {
            // Try to fetch a single property to test connectivity
            $response = $this->getPropertyDirectory(['per_page' => 1]);

            return true;
        } catch (\Exception $e) {
            Log::error('AppFolio connection test failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    // =========================================================================
    // AppFolio Reports API Methods
    // =========================================================================

    /**
     * Fetch property directory from Reports API.
     *
     * Returns detailed property information including address, sqft, unit counts,
     * portfolio, and year built.
     *
     * @param  array  $params  Request parameters:
     *                         - paginate_results: bool (default true)
     *                         - per_page: int (default 100, max 500)
     *                         - property_id: array (optional, filter by property IDs)
     */
    public function getPropertyDirectory(array $params = []): array
    {
        $defaults = [
            'paginate_results' => true,
            'per_page' => config('appfolio.sync.batch_size', 100),
        ];

        return $this->request('POST', self::REPORT_ENDPOINTS['property_directory'], array_merge($defaults, $params)) ?? [];
    }

    /**
     * Fetch unit directory from Reports API.
     *
     * Returns detailed unit information including sqft, bedrooms, bathrooms,
     * market rent, and current occupancy status.
     *
     * @param  array  $params  Request parameters:
     *                         - paginate_results: bool (default true)
     *                         - per_page: int (default 100, max 500)
     *                         - property_id: array (optional, filter by property IDs)
     *                         - unit_id: array (optional, filter by unit IDs)
     */
    public function getUnitDirectory(array $params = []): array
    {
        $defaults = [
            'paginate_results' => true,
            'per_page' => config('appfolio.sync.batch_size', 100),
        ];

        return $this->request('POST', self::REPORT_ENDPOINTS['unit_directory'], array_merge($defaults, $params)) ?? [];
    }

    /**
     * Fetch vendor directory from Reports API.
     *
     * Returns vendor profiles including company name, trades, contact info,
     * and insurance/workers comp expiration dates.
     *
     * @param  array  $params  Request parameters:
     *                         - paginate_results: bool (default true)
     *                         - per_page: int (default 100, max 500)
     *                         - vendor_id: array (optional, filter by vendor IDs)
     */
    public function getVendorDirectory(array $params = []): array
    {
        $defaults = [
            'paginate_results' => true,
            'per_page' => config('appfolio.sync.batch_size', 100),
        ];

        return $this->request('POST', self::REPORT_ENDPOINTS['vendor_directory'], array_merge($defaults, $params)) ?? [];
    }

    /**
     * Fetch work orders from Reports API.
     *
     * Returns work order details including vendor, costs, status, and dates.
     *
     * @param  array  $params  Request parameters:
     *                         - paginate_results: bool (default true)
     *                         - per_page: int (default 100, max 500)
     *                         - from_date: string (YYYY-MM-DD, required for date range)
     *                         - to_date: string (YYYY-MM-DD, required for date range)
     *                         - property_id: array (optional)
     *                         - status: string (optional: open, completed, cancelled)
     */
    public function getWorkOrderReport(array $params = []): array
    {
        $defaults = [
            'paginate_results' => true,
            'per_page' => config('appfolio.sync.batch_size', 100),
        ];

        return $this->request('POST', self::REPORT_ENDPOINTS['work_order'], array_merge($defaults, $params)) ?? [];
    }

    /**
     * Fetch expense register from Reports API.
     *
     * Returns expense records including GL account, vendor, amount, and dates.
     * Used for utility cost tracking and vendor spend analysis.
     *
     * @param  array  $params  Request parameters:
     *                         - paginate_results: bool (default true)
     *                         - per_page: int (default 100, max 500)
     *                         - from_date: string (YYYY-MM-DD, required)
     *                         - to_date: string (YYYY-MM-DD, required)
     *                         - property_id: array (optional)
     *                         - gl_account_id: array (optional, filter by GL accounts)
     */
    public function getExpenseRegister(array $params = []): array
    {
        $defaults = [
            'paginate_results' => true,
            'per_page' => config('appfolio.sync.batch_size', 100),
        ];

        return $this->request('POST', self::REPORT_ENDPOINTS['expense_register'], array_merge($defaults, $params)) ?? [];
    }

    /**
     * Fetch rent roll from Reports API.
     *
     * Returns current lease and rent information for all units.
     *
     * @param  array  $params  Request parameters:
     *                         - paginate_results: bool (default true)
     *                         - per_page: int (default 100, max 500)
     *                         - as_of_date: string (YYYY-MM-DD, default today)
     *                         - property_id: array (optional)
     */
    public function getRentRoll(array $params = []): array
    {
        $defaults = [
            'paginate_results' => true,
            'per_page' => config('appfolio.sync.batch_size', 100),
        ];

        return $this->request('POST', self::REPORT_ENDPOINTS['rent_roll'], array_merge($defaults, $params)) ?? [];
    }

    /**
     * Fetch delinquency report from Reports API.
     *
     * Returns delinquent accounts with amounts owed and aging buckets.
     *
     * @param  array  $params  Request parameters:
     *                         - paginate_results: bool (default true)
     *                         - per_page: int (default 100, max 500)
     *                         - as_of_date: string (YYYY-MM-DD, default today)
     *                         - property_id: array (optional)
     *                         - minimum_balance: float (optional, filter by minimum amount)
     */
    public function getDelinquency(array $params = []): array
    {
        $defaults = [
            'paginate_results' => true,
            'per_page' => config('appfolio.sync.batch_size', 100),
        ];

        return $this->request('POST', self::REPORT_ENDPOINTS['delinquency'], array_merge($defaults, $params)) ?? [];
    }

    /**
     * Fetch all pages of a paginated report endpoint.
     *
     * Handles pagination automatically by following next_page_url links.
     *
     * @param  string  $method  The report method name (e.g., 'getPropertyDirectory')
     * @param  array  $params  Initial request parameters
     * @param  callable|null  $onProgress  Optional callback for progress updates
     *                                     Signature: function(int $page, int $recordsFetched, bool $hasMore)
     * @param  int|null  $maxPages  Optional maximum number of pages to fetch (null = unlimited)
     * @return array All results combined from all pages
     *
     * @throws \Exception If pagination fails after retries
     */
    public function fetchAllPages(
        string $method,
        array $params = [],
        ?callable $onProgress = null,
        ?int $maxPages = null
    ): array {
        if (! method_exists($this, $method)) {
            throw new \InvalidArgumentException("Method {$method} does not exist");
        }

        $allResults = [];
        $page = 1;
        $totalRecords = 0;

        try {
            $response = $this->$method($params);

            // Combine results from first page
            if (isset($response['results'])) {
                $allResults = array_merge($allResults, $response['results']);
                $totalRecords = count($allResults);
            }

            $hasMore = ! empty($response['next_page_url']);

            // Report progress for first page
            if ($onProgress) {
                $onProgress($page, $totalRecords, $hasMore);
            }

            // Follow pagination
            while ($hasMore) {
                $page++;

                // Check max pages limit
                if ($maxPages !== null && $page > $maxPages) {
                    Log::info('Pagination stopped at max pages limit', [
                        'method' => $method,
                        'max_pages' => $maxPages,
                        'records_fetched' => $totalRecords,
                    ]);
                    break;
                }

                Log::debug('Following pagination', [
                    'method' => $method,
                    'page' => $page,
                    'url' => $response['next_page_url'],
                ]);

                // Make request to the next page URL directly
                $response = $this->request('POST', $response['next_page_url'], []) ?? [];

                if (isset($response['results'])) {
                    $allResults = array_merge($allResults, $response['results']);
                    $totalRecords = count($allResults);
                }

                $hasMore = ! empty($response['next_page_url']);

                // Report progress
                if ($onProgress) {
                    $onProgress($page, $totalRecords, $hasMore);
                }
            }

            Log::info('Pagination complete', [
                'method' => $method,
                'total_pages' => $page,
                'total_records' => $totalRecords,
            ]);

        } catch (\Exception $e) {
            Log::error('Pagination failed', [
                'method' => $method,
                'page' => $page,
                'records_fetched' => $totalRecords,
                'error' => $e->getMessage(),
            ]);

            // If we have some results, return them with a warning
            if ($totalRecords > 0) {
                Log::warning('Returning partial results due to pagination error', [
                    'records_fetched' => $totalRecords,
                ]);

                return $allResults;
            }

            throw $e;
        }

        return $allResults;
    }

    /**
     * Get pagination statistics for a report.
     *
     * Fetches just the first page to determine total count and page information.
     *
     * @param  string  $method  The report method name
     * @param  array  $params  Request parameters
     * @return array Pagination statistics including estimated total pages
     */
    public function getPaginationInfo(string $method, array $params = []): array
    {
        if (! method_exists($this, $method)) {
            throw new \InvalidArgumentException("Method {$method} does not exist");
        }

        $params['per_page'] = 1; // Minimal request to get pagination info
        $response = $this->$method($params);

        $perPage = config('appfolio.sync.batch_size', 100);

        return [
            'has_results' => isset($response['results']) && count($response['results']) > 0,
            'has_more_pages' => ! empty($response['next_page_url']),
            'per_page' => $perPage,
            // Note: AppFolio API doesn't provide total_count, so we can't estimate total pages
        ];
    }
}
