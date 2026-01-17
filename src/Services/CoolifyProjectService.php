<?php

declare(strict_types=1);

namespace Stumason\Coolify\Services;

use Illuminate\Support\Facades\Cache;
use Stumason\Coolify\Contracts\ProjectRepository;
use Stumason\Coolify\Exceptions\CoolifyApiException;

/**
 * Service for discovering and caching Coolify project resources.
 *
 * Uses COOLIFY_PROJECT_UUID as the single source of truth, then fetches
 * environments and resources from the Coolify API on-demand with caching.
 */
class CoolifyProjectService
{
    /**
     * Cache key prefix for project data.
     */
    protected const CACHE_PREFIX = 'coolify:project:';

    public function __construct(
        protected ProjectRepository $projects
    ) {}

    /**
     * Get the configured project UUID.
     */
    public function getProjectUuid(): ?string
    {
        return config('coolify.project_uuid');
    }

    /**
     * Get the configured environment name.
     */
    public function getEnvironment(): string
    {
        return config('coolify.environment', 'production');
    }

    /**
     * Get project details.
     */
    public function getProject(): ?array
    {
        $projectUuid = $this->getProjectUuid();

        if (! $projectUuid) {
            return null;
        }

        return $this->cached("project:{$projectUuid}", function () use ($projectUuid) {
            return $this->projects->get($projectUuid);
        });
    }

    /**
     * Get all environments for the configured project.
     */
    public function getEnvironments(): array
    {
        $projectUuid = $this->getProjectUuid();

        if (! $projectUuid) {
            return [];
        }

        return $this->cached("environments:{$projectUuid}", function () use ($projectUuid) {
            return $this->projects->environments($projectUuid);
        });
    }

    /**
     * Get the current environment's resources.
     */
    public function getCurrentEnvironment(): ?array
    {
        $projectUuid = $this->getProjectUuid();
        $environment = $this->getEnvironment();

        if (! $projectUuid) {
            return null;
        }

        return $this->cached("environment:{$projectUuid}:{$environment}", function () use ($projectUuid, $environment) {
            try {
                return $this->projects->environment($projectUuid, $environment);
            } catch (CoolifyApiException) {
                return null;
            }
        });
    }

    /**
     * Get all applications in the current environment.
     */
    public function getApplications(): array
    {
        $environment = $this->getCurrentEnvironment();

        if (! $environment) {
            return [];
        }

        return $environment['applications'] ?? [];
    }

    /**
     * Get all databases in the current environment.
     */
    public function getDatabases(): array
    {
        $environment = $this->getCurrentEnvironment();

        if (! $environment) {
            return [];
        }

        // Coolify returns different types of databases
        $databases = [];

        foreach (['postgresqls', 'mysqls', 'mariadbs', 'mongodbs', 'redis', 'dragonflies', 'keydbs', 'clickhouses'] as $type) {
            if (! empty($environment[$type])) {
                foreach ($environment[$type] as $db) {
                    $db['_type'] = $type;
                    $databases[] = $db;
                }
            }
        }

        return $databases;
    }

    /**
     * Get all services in the current environment.
     */
    public function getServices(): array
    {
        $environment = $this->getCurrentEnvironment();

        if (! $environment) {
            return [];
        }

        return $environment['services'] ?? [];
    }

    /**
     * Get the default application UUID.
     *
     * Priority:
     * 1. COOLIFY_APPLICATION_UUID env var
     * 2. First application in the environment
     */
    public function getApplicationUuid(): ?string
    {
        // Check for explicit config first
        $configuredUuid = config('coolify.application_uuid');
        if ($configuredUuid) {
            return $configuredUuid;
        }

        // Fall back to discovering from project
        $applications = $this->getApplications();

        return $applications[0]['uuid'] ?? null;
    }

    /**
     * Get a specific application by UUID or name.
     */
    public function getApplication(?string $identifier = null): ?array
    {
        if (! $identifier) {
            $identifier = $this->getApplicationUuid();
        }

        if (! $identifier) {
            return null;
        }

        $applications = $this->getApplications();

        foreach ($applications as $app) {
            if (($app['uuid'] ?? null) === $identifier || ($app['name'] ?? null) === $identifier) {
                return $app;
            }
        }

        return null;
    }

    /**
     * Get the default database UUID.
     *
     * Priority:
     * 1. COOLIFY_DATABASE_UUID env var
     * 2. First PostgreSQL/MySQL database in the environment
     */
    public function getDatabaseUuid(): ?string
    {
        // Check for explicit config first
        $configuredUuid = config('coolify.database_uuid');
        if ($configuredUuid) {
            return $configuredUuid;
        }

        // Fall back to discovering from project
        $databases = $this->getDatabases();

        // Prefer PostgreSQL, then MySQL
        foreach ($databases as $db) {
            $type = $db['_type'] ?? '';
            if (in_array($type, ['postgresqls', 'mysqls', 'mariadbs'])) {
                return $db['uuid'] ?? null;
            }
        }

        return $databases[0]['uuid'] ?? null;
    }

    /**
     * Get the default Redis/Dragonfly UUID.
     *
     * Priority:
     * 1. COOLIFY_REDIS_UUID env var
     * 2. First Redis/Dragonfly in the environment
     */
    public function getRedisUuid(): ?string
    {
        // Check for explicit config first
        $configuredUuid = config('coolify.redis_uuid');
        if ($configuredUuid) {
            return $configuredUuid;
        }

        // Fall back to discovering from project
        $databases = $this->getDatabases();

        foreach ($databases as $db) {
            $type = $db['_type'] ?? '';
            if (in_array($type, ['redis', 'dragonflies', 'keydbs'])) {
                return $db['uuid'] ?? null;
            }
        }

        return null;
    }

    /**
     * Get the server UUID from the configured project.
     */
    public function getServerUuid(): ?string
    {
        // Check for explicit config first
        $configuredUuid = config('coolify.server_uuid');
        if ($configuredUuid) {
            return $configuredUuid;
        }

        // Try to get from an application
        $app = $this->getApplication();
        if ($app && isset($app['destination']['server']['uuid'])) {
            return $app['destination']['server']['uuid'];
        }

        return null;
    }

    /**
     * Check if the project is configured.
     */
    public function isConfigured(): bool
    {
        return $this->getProjectUuid() !== null;
    }

    /**
     * Clear all cached project data.
     */
    public function clearCache(): void
    {
        $projectUuid = $this->getProjectUuid();
        $environment = $this->getEnvironment();

        if ($projectUuid) {
            Cache::forget(self::CACHE_PREFIX."project:{$projectUuid}");
            Cache::forget(self::CACHE_PREFIX."environments:{$projectUuid}");
            Cache::forget(self::CACHE_PREFIX."environment:{$projectUuid}:{$environment}");
        }
    }

    /**
     * Get or cache a value.
     */
    protected function cached(string $key, callable $callback): mixed
    {
        $ttl = config('coolify.cache_ttl', 30);

        if ($ttl <= 0) {
            return $callback();
        }

        return Cache::remember(self::CACHE_PREFIX.$key, $ttl, $callback);
    }

    /**
     * Get all discovered resources as a summary.
     */
    public function getResourceSummary(): array
    {
        return [
            'project_uuid' => $this->getProjectUuid(),
            'environment' => $this->getEnvironment(),
            'application_uuid' => $this->getApplicationUuid(),
            'database_uuid' => $this->getDatabaseUuid(),
            'redis_uuid' => $this->getRedisUuid(),
            'server_uuid' => $this->getServerUuid(),
            'applications' => array_map(fn ($a) => [
                'uuid' => $a['uuid'] ?? null,
                'name' => $a['name'] ?? null,
                'status' => $a['status'] ?? null,
            ], $this->getApplications()),
            'databases' => array_map(fn ($d) => [
                'uuid' => $d['uuid'] ?? null,
                'name' => $d['name'] ?? null,
                'type' => $d['_type'] ?? null,
            ], $this->getDatabases()),
            'services' => array_map(fn ($s) => [
                'uuid' => $s['uuid'] ?? null,
                'name' => $s['name'] ?? null,
            ], $this->getServices()),
        ];
    }
}
