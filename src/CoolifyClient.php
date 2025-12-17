<?php

namespace Stumason\Coolify;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Stumason\Coolify\Exceptions\CoolifyApiException;
use Stumason\Coolify\Exceptions\CoolifyAuthenticationException;
use Stumason\Coolify\Exceptions\CoolifyNotFoundException;

class CoolifyClient
{
    /**
     * The base URL of the Coolify API.
     */
    protected string $baseUrl;

    /**
     * The API token for authentication.
     */
    protected ?string $token;

    /**
     * The team ID for API requests.
     */
    protected ?string $teamId;

    /**
     * Create a new Coolify client instance.
     */
    public function __construct(
        string $baseUrl,
        ?string $token = null,
        ?string $teamId = null
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->token = $token;
        $this->teamId = $teamId;
    }

    /**
     * Make a GET request to the Coolify API.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws CoolifyApiException
     */
    public function get(string $endpoint, array $query = [], bool $cached = true): array
    {
        if ($cached && ($ttl = config('coolify.cache_ttl', 30)) > 0) {
            $cacheKey = $this->cacheKey('get', $endpoint, $query);

            return Cache::remember($cacheKey, $ttl, function () use ($endpoint, $query) {
                return $this->request('get', $endpoint, ['query' => $query]);
            });
        }

        return $this->request('get', $endpoint, ['query' => $query]);
    }

    /**
     * Make a POST request to the Coolify API.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws CoolifyApiException
     */
    public function post(string $endpoint, array $data = []): array
    {
        $this->clearCacheForEndpoint($endpoint);

        return $this->request('post', $endpoint, ['json' => $data]);
    }

    /**
     * Make a PUT request to the Coolify API.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws CoolifyApiException
     */
    public function put(string $endpoint, array $data = []): array
    {
        $this->clearCacheForEndpoint($endpoint);

        return $this->request('put', $endpoint, ['json' => $data]);
    }

    /**
     * Make a PATCH request to the Coolify API.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws CoolifyApiException
     */
    public function patch(string $endpoint, array $data = []): array
    {
        $this->clearCacheForEndpoint($endpoint);

        return $this->request('patch', $endpoint, ['json' => $data]);
    }

    /**
     * Make a DELETE request to the Coolify API.
     *
     * @return array<string, mixed>
     *
     * @throws CoolifyApiException
     */
    public function delete(string $endpoint): array
    {
        $this->clearCacheForEndpoint($endpoint);

        return $this->request('delete', $endpoint);
    }

    /**
     * Make a request to the Coolify API.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     *
     * @throws CoolifyApiException
     * @throws CoolifyAuthenticationException
     * @throws CoolifyNotFoundException
     */
    protected function request(string $method, string $endpoint, array $options = []): array
    {
        $response = $this->buildRequest()
            ->{$method}($this->buildUrl($endpoint), $options['json'] ?? $options['query'] ?? []);

        return $this->handleResponse($response);
    }

    /**
     * Build the HTTP request with authentication.
     */
    protected function buildRequest(): PendingRequest
    {
        $request = Http::acceptJson()
            ->timeout(30)
            ->retry(3, 100, throw: false);

        if ($this->token) {
            $request->withToken($this->token);
        }

        return $request;
    }

    /**
     * Build the full URL for an API endpoint.
     */
    protected function buildUrl(string $endpoint): string
    {
        $endpoint = ltrim($endpoint, '/');

        return "{$this->baseUrl}/api/v1/{$endpoint}";
    }

    /**
     * Handle the API response.
     *
     * @return array<string, mixed>
     *
     * @throws CoolifyApiException
     * @throws CoolifyAuthenticationException
     * @throws CoolifyNotFoundException
     */
    protected function handleResponse(Response $response): array
    {
        if ($response->successful()) {
            return $response->json() ?? [];
        }

        if ($response->status() === 401) {
            throw new CoolifyAuthenticationException(
                'Invalid or missing Coolify API token. Please check your COOLIFY_TOKEN configuration.'
            );
        }

        if ($response->status() === 404) {
            throw new CoolifyNotFoundException(
                'The requested resource was not found in Coolify.'
            );
        }

        throw new CoolifyApiException(
            $response->json('message') ?? "Coolify API request failed with status {$response->status()}",
            $response->status()
        );
    }

    /**
     * Generate a cache key for a request.
     *
     * @param  array<string, mixed>  $params
     */
    protected function cacheKey(string $method, string $endpoint, array $params = []): string
    {
        return 'coolify:'.md5($method.$endpoint.json_encode($params));
    }

    /**
     * Clear cached responses for an endpoint.
     */
    protected function clearCacheForEndpoint(string $endpoint): void
    {
        // Extract the resource type from the endpoint (e.g., 'applications' from 'applications/uuid')
        $parts = explode('/', trim($endpoint, '/'));
        $resource = $parts[0] ?? '';

        Cache::forget("coolify:{$resource}:list");
    }

    /**
     * Clear all cached Coolify responses.
     */
    public function clearCache(): void
    {
        // This is a simple implementation - in production you might want
        // to use cache tags if your cache driver supports them
        Cache::flush();
    }

    /**
     * Check if the client is configured with valid credentials.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->token) && ! empty($this->baseUrl);
    }

    /**
     * Test the connection to the Coolify API.
     *
     * @throws CoolifyApiException
     */
    public function testConnection(): bool
    {
        try {
            $this->get('version', cached: false);

            return true;
        } catch (CoolifyApiException) {
            return false;
        }
    }

    /**
     * Get the Coolify API version.
     *
     * @return array<string, mixed>
     */
    public function version(): array
    {
        return $this->get('version', cached: false);
    }

    /**
     * Check if the Coolify API is healthy.
     *
     * @return array<string, mixed>
     */
    public function health(): array
    {
        return $this->get('health', cached: false);
    }
}
