<?php

declare(strict_types=1);

namespace Stumason\Coolify\Repositories;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Stumason\Coolify\Contracts\ApplicationRepository;
use Stumason\Coolify\Contracts\KickRepository;
use Stumason\Coolify\Exceptions\CoolifyApiException;
use Stumason\Coolify\Exceptions\CoolifyAuthenticationException;
use Stumason\Coolify\Exceptions\CoolifyNotFoundException;
use Stumason\Coolify\Exceptions\KickApiException;
use Stumason\Coolify\Exceptions\KickAuthenticationException;
use Stumason\Coolify\Exceptions\KickUnavailableException;
use Stumason\Coolify\Services\KickClient;

/**
 * KickRepository implementation that fetches kick config from Coolify
 * and proxies requests to remote Laravel Kick endpoints.
 */
class CoolifyKickRepository implements KickRepository
{
    public function __construct(
        protected ApplicationRepository $applications,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function getConfig(string $appUuid): ?array
    {
        $ttl = config('coolify.kick.cache_ttl', 60);

        return Cache::remember("kick.config.{$appUuid}", $ttl, function () use ($appUuid) {
            return $this->fetchConfig($appUuid);
        });
    }

    /**
     * Fetch and validate kick configuration from an app's environment.
     *
     * @return array{base_url: string, token: string, kick_path: string}|null
     */
    protected function fetchConfig(string $appUuid): ?array
    {
        try {
            $envs = $this->applications->envs($appUuid);
        } catch (CoolifyNotFoundException $e) {
            Log::debug('Kick: Application not found when fetching config', ['app_uuid' => $appUuid]);

            return null;
        } catch (CoolifyAuthenticationException $e) {
            Log::error('Kick: Coolify authentication failed', [
                'app_uuid' => $appUuid,
                'error' => $e->getMessage(),
            ]);

            return null;
        } catch (CoolifyApiException $e) {
            Log::warning('Kick: Coolify API error fetching env vars', [
                'app_uuid' => $appUuid,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $envsCollection = collect($envs);

        $token = $envsCollection->firstWhere('key', 'KICK_TOKEN')['value'] ?? null;
        $enabled = $envsCollection->firstWhere('key', 'KICK_ENABLED')['value'] ?? 'false';

        if (! $token || strtolower($enabled) !== 'true') {
            return null;
        }

        try {
            $app = $this->applications->get($appUuid);
        } catch (CoolifyNotFoundException $e) {
            Log::debug('Kick: Application not found', ['app_uuid' => $appUuid]);

            return null;
        } catch (CoolifyApiException $e) {
            Log::warning('Kick: Coolify API error fetching application', [
                'app_uuid' => $appUuid,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $fqdn = $app['fqdn'] ?? null;
        if (! $fqdn) {
            Log::debug('Kick: Application has no FQDN configured', ['app_uuid' => $appUuid]);

            return null;
        }

        // fqdn can contain multiple URLs separated by commas - use the first one
        $baseUrl = rtrim(explode(',', $fqdn)[0], '/');

        $kickPath = $envsCollection->firstWhere('key', 'KICK_PREFIX')['value'] ?? 'kick';

        return [
            'base_url' => $baseUrl,
            'token' => $token,
            'kick_path' => $kickPath,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function isReachable(string $appUuid): bool
    {
        $client = $this->client($appUuid);
        if (! $client) {
            return false;
        }

        return $client->isReachable();
    }

    /**
     * {@inheritDoc}
     */
    public function health(string $appUuid): ?array
    {
        return $this->call($appUuid, fn (KickClient $client) => $client->health());
    }

    /**
     * {@inheritDoc}
     */
    public function stats(string $appUuid): ?array
    {
        return $this->call($appUuid, fn (KickClient $client) => $client->stats());
    }

    /**
     * {@inheritDoc}
     */
    public function logFiles(string $appUuid): ?array
    {
        return $this->call($appUuid, fn (KickClient $client) => $client->logFiles());
    }

    /**
     * {@inheritDoc}
     */
    public function logRead(string $appUuid, string $file, ?string $level = null, ?string $search = null, int $lines = 100): ?array
    {
        return $this->call($appUuid, fn (KickClient $client) => $client->logRead($file, $level, $search, $lines));
    }

    /**
     * {@inheritDoc}
     */
    public function queueStatus(string $appUuid): ?array
    {
        return $this->call($appUuid, fn (KickClient $client) => $client->queueStatus());
    }

    /**
     * {@inheritDoc}
     */
    public function queueFailed(string $appUuid, int $limit = 20): ?array
    {
        return $this->call($appUuid, fn (KickClient $client) => $client->queueFailed($limit));
    }

    /**
     * {@inheritDoc}
     */
    public function logsTest(string $appUuid): ?array
    {
        return $this->call($appUuid, fn (KickClient $client) => $client->logsTest());
    }

    /**
     * {@inheritDoc}
     */
    public function artisanList(string $appUuid): ?array
    {
        return $this->call($appUuid, fn (KickClient $client) => $client->artisanList());
    }

    /**
     * {@inheritDoc}
     */
    public function artisanRun(string $appUuid, string $command, array $arguments = []): ?array
    {
        return $this->call($appUuid, fn (KickClient $client) => $client->artisanRun($command, $arguments));
    }

    /**
     * Get or create a KickClient instance for an application.
     */
    protected function client(string $appUuid): ?KickClient
    {
        $config = $this->getConfig($appUuid);
        if (! $config) {
            return null;
        }

        return new KickClient(
            baseUrl: $config['base_url'].'/'.$config['kick_path'],
            token: $config['token'],
            timeout: config('coolify.kick.timeout', 10),
        );
    }

    /**
     * Execute a callback with a KickClient, returning null if not available.
     *
     * @template T
     *
     * @param  callable(KickClient): T  $callback
     * @return T|null
     */
    protected function call(string $appUuid, callable $callback): mixed
    {
        $client = $this->client($appUuid);
        if (! $client) {
            return null;
        }

        try {
            return $callback($client);
        } catch (KickAuthenticationException $e) {
            Log::warning('Kick: Authentication failed', [
                'app_uuid' => $appUuid,
                'error' => $e->getMessage(),
            ]);

            return null;
        } catch (KickUnavailableException $e) {
            Log::info('Kick: Service unavailable', [
                'app_uuid' => $appUuid,
                'error' => $e->getMessage(),
            ]);

            return null;
        } catch (KickApiException $e) {
            Log::error('Kick: API error', [
                'app_uuid' => $appUuid,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return null;
        }
    }

    /**
     * Clear cached kick configuration for an application.
     */
    public function clearCache(string $appUuid): void
    {
        Cache::forget("kick.config.{$appUuid}");
    }
}
