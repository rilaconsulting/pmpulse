<?php

namespace App\Services;

use App\Models\AppfolioConnection;
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
 * TODO: Replace mock endpoints with actual AppFolio API endpoints
 * once API documentation is provided.
 */
class AppfolioClient
{
    private ?AppfolioConnection $connection = null;

    private int $retryCount = 0;

    /**
     * API endpoint paths.
     *
     * TODO: These are placeholder endpoints. Replace with actual AppFolio API paths
     * when documentation becomes available. The structure may vary based on
     * AppFolio's actual API design.
     */
    private const ENDPOINTS = [
        'properties' => '/v1/properties',           // TODO: Verify actual endpoint
        'units' => '/v1/units',                     // TODO: Verify actual endpoint
        'people' => '/v1/people',                   // TODO: Verify actual endpoint
        'leases' => '/v1/leases',                   // TODO: Verify actual endpoint
        'ledger_transactions' => '/v1/ledger',      // TODO: Verify actual endpoint
        'work_orders' => '/v1/work-orders',         // TODO: Verify actual endpoint
    ];

    public function __construct()
    {
        $this->connection = AppfolioConnection::first();
    }

    /**
     * Set a specific connection to use.
     */
    public function setConnection(AppfolioConnection $connection): self
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Check if the client is properly configured.
     */
    public function isConfigured(): bool
    {
        return $this->connection && $this->connection->isConfigured();
    }

    /**
     * Get the base HTTP client with authentication.
     */
    private function getHttpClient(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('AppFolio connection is not configured');
        }

        $baseUrl = $this->connection->api_base_url;
        $clientId = $this->connection->client_id;
        $clientSecret = $this->connection->client_secret;

        // TODO: Adjust authentication method based on AppFolio's actual API
        // This assumes OAuth2 Client Credentials or Basic Auth
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
     * Fetch properties from AppFolio.
     *
     * TODO: Adjust field mapping based on actual AppFolio API response structure.
     *
     * @param  array  $params  Query parameters (e.g., modified_since, page, per_page)
     */
    public function getProperties(array $params = []): array
    {
        return $this->request('GET', self::ENDPOINTS['properties'], $params) ?? [];
    }

    /**
     * Fetch units from AppFolio.
     *
     * TODO: Adjust field mapping based on actual AppFolio API response structure.
     *
     * @param  array  $params  Query parameters
     */
    public function getUnits(array $params = []): array
    {
        return $this->request('GET', self::ENDPOINTS['units'], $params) ?? [];
    }

    /**
     * Fetch people (tenants, owners, etc.) from AppFolio.
     *
     * TODO: Adjust field mapping based on actual AppFolio API response structure.
     *
     * @param  array  $params  Query parameters
     */
    public function getPeople(array $params = []): array
    {
        return $this->request('GET', self::ENDPOINTS['people'], $params) ?? [];
    }

    /**
     * Fetch leases from AppFolio.
     *
     * TODO: Adjust field mapping based on actual AppFolio API response structure.
     *
     * @param  array  $params  Query parameters
     */
    public function getLeases(array $params = []): array
    {
        return $this->request('GET', self::ENDPOINTS['leases'], $params) ?? [];
    }

    /**
     * Fetch ledger transactions from AppFolio.
     *
     * TODO: Adjust field mapping based on actual AppFolio API response structure.
     *
     * @param  array  $params  Query parameters (e.g., start_date, end_date)
     */
    public function getLedgerTransactions(array $params = []): array
    {
        return $this->request('GET', self::ENDPOINTS['ledger_transactions'], $params) ?? [];
    }

    /**
     * Fetch work orders from AppFolio.
     *
     * TODO: Adjust field mapping based on actual AppFolio API response structure.
     *
     * @param  array  $params  Query parameters
     */
    public function getWorkOrders(array $params = []): array
    {
        return $this->request('GET', self::ENDPOINTS['work_orders'], $params) ?? [];
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
            $response = $this->getProperties(['per_page' => 1]);

            return true;
        } catch (\Exception $e) {
            Log::error('AppFolio connection test failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
