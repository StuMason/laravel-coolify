<?php

namespace Stumason\Coolify;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Stumason\Coolify\Exceptions\CoolifyApiException;
use Stumason\Coolify\Exceptions\CoolifyAuthenticationException;
use Stumason\Coolify\Exceptions\CoolifyNotFoundException;

class CoolifyClient
{
    /**
     * Cache key holding the registry of keys this client has written.
     */
    protected const CACHE_REGISTRY = 'coolify:cache-keys';

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
            $this->registerCacheKey($cacheKey);

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
    public function post(string $endpoint, array $data = [], ?int $timeout = null): array
    {
        $this->clearCache();

        return $this->request('post', $endpoint, ['json' => $data], $timeout);
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
        $this->clearCache();

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
        $this->clearCache();

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
        $this->clearCache();

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
    protected function request(string $method, string $endpoint, array $options = [], ?int $timeout = null): array
    {
        $response = $this->buildRequest($timeout, retry: $method === 'get')
            ->{$method}($this->buildUrl($endpoint), $options['json'] ?? $options['query'] ?? []);

        return $this->handleResponse($response);
    }

    /**
     * Build the HTTP request with authentication.
     *
     * Retries are GET-only: a timed-out POST to a deploy/restart endpoint
     * may already have been acted on server-side, and retrying it fires
     * the action again.
     */
    protected function buildRequest(?int $timeout = null, bool $retry = false): PendingRequest
    {
        $request = Http::acceptJson()
            ->timeout($timeout ?? config('coolify.timeout', 60));

        if ($retry) {
            $request->retry(3, 100, throw: false);
        }

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

        $message = $response->json('message') ?? "Coolify API request failed with status {$response->status()}";
        $errors = $response->json('errors');

        if (is_array($errors) && $errors !== []) {
            $details = collect($errors)
                ->map(fn ($messages, $field) => "{$field}: ".implode(' ', Arr::flatten((array) $messages)))
                ->implode('; ');

            $message = "{$message} ({$details})";
        }

        throw new CoolifyApiException($message, $response->status());
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
     * Record a cache key in the registry so clearCache() can forget it
     * without flushing the host application's cache store.
     */
    protected function registerCacheKey(string $key): void
    {
        $keys = Cache::get(self::CACHE_REGISTRY, []);

        if (! in_array($key, $keys, true)) {
            $keys[] = $key;
            Cache::put(self::CACHE_REGISTRY, $keys, now()->addDay());
        }
    }

    /**
     * Clear all cached Coolify responses.
     *
     * Only forgets keys this client wrote (tracked in a registry) — never
     * the host application's cache. Called before every mutation so the
     * dashboard can't serve pre-mutation state for the rest of the TTL.
     */
    public function clearCache(): void
    {
        foreach (Cache::pull(self::CACHE_REGISTRY, []) as $key) {
            Cache::forget($key);
        }
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
