<?php

declare(strict_types=1);

namespace Stumason\Coolify\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Stumason\Coolify\Exceptions\KickApiException;
use Stumason\Coolify\Exceptions\KickAuthenticationException;
use Stumason\Coolify\Exceptions\KickUnavailableException;

/**
 * HTTP client for communicating with Laravel Kick endpoints on remote apps.
 */
class KickClient
{
    /**
     * Create a new Kick client instance.
     */
    public function __construct(
        protected string $baseUrl,
        protected string $token,
        protected int $timeout = 10,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Get health check status from the remote app.
     *
     * @return array<string, mixed>
     *
     * @throws KickApiException
     */
    public function health(): array
    {
        return $this->get('/health');
    }

    /**
     * Get system stats from the remote app.
     *
     * @return array<string, mixed>
     *
     * @throws KickApiException
     */
    public function stats(): array
    {
        return $this->get('/stats');
    }

    /**
     * List available log files.
     *
     * @return array<string, mixed>
     *
     * @throws KickApiException
     */
    public function logFiles(): array
    {
        return $this->get('/logs');
    }

    /**
     * Read entries from a log file.
     *
     * @return array<string, mixed>
     *
     * @throws KickApiException
     */
    public function logRead(string $file, ?string $level = null, ?string $search = null, int $lines = 100): array
    {
        $query = ['lines' => $lines];

        if ($level !== null) {
            $query['level'] = $level;
        }

        if ($search !== null) {
            $query['search'] = $search;
        }

        return $this->get("/logs/{$file}", $query);
    }

    /**
     * Get queue status.
     *
     * @return array<string, mixed>
     *
     * @throws KickApiException
     */
    public function queueStatus(): array
    {
        return $this->get('/queue');
    }

    /**
     * Get failed jobs.
     *
     * @return array<string, mixed>
     *
     * @throws KickApiException
     */
    public function queueFailed(int $limit = 20): array
    {
        return $this->get('/queue/failed', ['limit' => $limit]);
    }

    /**
     * Write test log entries (creates entries for each log level).
     *
     * @return array<string, mixed>
     *
     * @throws KickApiException
     */
    public function logsTest(): array
    {
        return $this->post('/logs/test');
    }

    /**
     * List available artisan commands.
     *
     * @return array<string, mixed>
     *
     * @throws KickApiException
     */
    public function artisanList(): array
    {
        return $this->get('/artisan');
    }

    /**
     * Execute an artisan command.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     *
     * @throws KickApiException
     */
    public function artisanRun(string $command, array $arguments = []): array
    {
        $payload = ['command' => $command];

        if (! empty($arguments)) {
            $payload['arguments'] = $arguments;
        }

        return $this->post('/artisan', $payload);
    }

    /**
     * Test if the kick endpoints are reachable.
     */
    public function isReachable(): bool
    {
        try {
            $this->health();

            return true;
        } catch (KickApiException) {
            return false;
        }
    }

    /**
     * Make a GET request to a kick endpoint.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws KickApiException
     */
    protected function get(string $endpoint, array $query = []): array
    {
        $response = $this->buildRequest()
            ->get($this->buildUrl($endpoint), $query);

        return $this->handleResponse($response);
    }

    /**
     * Make a POST request to a kick endpoint.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws KickApiException
     */
    protected function post(string $endpoint, array $data = []): array
    {
        $response = $this->buildRequest()
            ->post($this->buildUrl($endpoint), $data);

        return $this->handleResponse($response);
    }

    /**
     * Build the HTTP request with authentication.
     */
    protected function buildRequest(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout($this->timeout)
            ->withToken($this->token)
            ->retry(2, 100, throw: false);
    }

    /**
     * Build the full URL for a kick endpoint.
     */
    protected function buildUrl(string $endpoint): string
    {
        $endpoint = ltrim($endpoint, '/');

        return "{$this->baseUrl}/{$endpoint}";
    }

    /**
     * Handle the API response.
     *
     * @return array<string, mixed>
     *
     * @throws KickApiException
     * @throws KickAuthenticationException
     * @throws KickUnavailableException
     */
    protected function handleResponse(Response $response): array
    {
        if ($response->successful()) {
            return $response->json() ?? [];
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new KickAuthenticationException(
                'Invalid or missing Kick token. Check the KICK_TOKEN environment variable on the application.'
            );
        }

        if ($response->serverError() || $response->status() === 0) {
            throw new KickUnavailableException(
                'Kick endpoints are not responding. Ensure laravel-kick is installed and the app is running.'
            );
        }

        throw new KickApiException(
            $response->json('message') ?? "Kick API request failed with status {$response->status()}",
            $response->status()
        );
    }
}
