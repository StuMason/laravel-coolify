<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Stumason\Coolify\Services\CoolifyProjectService;

beforeEach(function () {
    Http::preventStrayRequests();
    Cache::flush();
});

describe('CoolifyProjectService', function () {
    it('returns null when project UUID is not configured', function () {
        config([
            'coolify.project_uuid' => null,
            'coolify.application_uuid' => null,
        ]);

        $service = app(CoolifyProjectService::class);

        expect($service->getProjectUuid())->toBeNull()
            ->and($service->isConfigured())->toBeFalse()
            ->and($service->getApplicationUuid())->toBeNull();
    });

    it('returns configured project UUID', function () {
        config(['coolify.project_uuid' => 'test-project-uuid']);

        $service = app(CoolifyProjectService::class);

        expect($service->getProjectUuid())->toBe('test-project-uuid')
            ->and($service->isConfigured())->toBeTrue();
    });

    it('returns configured application UUID directly', function () {
        config([
            'coolify.project_uuid' => null,
            'coolify.application_uuid' => 'direct-app-uuid',
        ]);

        $service = app(CoolifyProjectService::class);

        expect($service->getApplicationUuid())->toBe('direct-app-uuid');
    });

    it('returns configured database UUID directly', function () {
        config([
            'coolify.project_uuid' => null,
            'coolify.database_uuid' => 'direct-db-uuid',
        ]);

        $service = app(CoolifyProjectService::class);

        expect($service->getDatabaseUuid())->toBe('direct-db-uuid');
    });

    it('returns configured redis UUID directly', function () {
        config([
            'coolify.project_uuid' => null,
            'coolify.redis_uuid' => 'direct-redis-uuid',
        ]);

        $service = app(CoolifyProjectService::class);

        expect($service->getRedisUuid())->toBe('direct-redis-uuid');
    });

    it('discovers application UUID from project environment', function () {
        config([
            'coolify.project_uuid' => 'test-project-uuid',
            'coolify.environment' => 'production',
            'coolify.application_uuid' => null,
        ]);

        Http::fake([
            '*/projects/test-project-uuid/production' => Http::response([
                'applications' => [
                    ['uuid' => 'discovered-app-uuid', 'name' => 'My App'],
                ],
                'postgresqls' => [],
                'redis' => [],
            ], 200),
        ]);

        $service = app(CoolifyProjectService::class);

        expect($service->getApplicationUuid())->toBe('discovered-app-uuid');
    });

    it('discovers database UUID from project environment', function () {
        config([
            'coolify.project_uuid' => 'test-project-uuid',
            'coolify.environment' => 'production',
            'coolify.database_uuid' => null,
        ]);

        Http::fake([
            '*/projects/test-project-uuid/production' => Http::response([
                'applications' => [],
                'postgresqls' => [
                    ['uuid' => 'discovered-db-uuid', 'name' => 'My DB'],
                ],
                'redis' => [],
            ], 200),
        ]);

        $service = app(CoolifyProjectService::class);

        expect($service->getDatabaseUuid())->toBe('discovered-db-uuid');
    });

    it('discovers redis UUID from project environment', function () {
        config([
            'coolify.project_uuid' => 'test-project-uuid',
            'coolify.environment' => 'production',
            'coolify.redis_uuid' => null,
        ]);

        Http::fake([
            '*/projects/test-project-uuid/production' => Http::response([
                'applications' => [],
                'postgresqls' => [],
                'dragonflies' => [
                    ['uuid' => 'discovered-redis-uuid', 'name' => 'My Cache'],
                ],
            ], 200),
        ]);

        $service = app(CoolifyProjectService::class);

        expect($service->getRedisUuid())->toBe('discovered-redis-uuid');
    });

    it('caches project environment data', function () {
        config([
            'coolify.project_uuid' => 'cache-test-project-uuid',
            'coolify.environment' => 'production',
            'coolify.cache_ttl' => 30,
            'coolify.application_uuid' => null,
        ]);

        Http::fake([
            '*/projects/cache-test-project-uuid/production' => Http::response([
                'applications' => [
                    ['uuid' => 'cached-app-uuid', 'name' => 'Cached App'],
                ],
            ], 200),
        ]);

        $service = app(CoolifyProjectService::class);

        // First call - should hit API
        $service->getApplicationUuid();

        // Second call - should use cache
        $service->getApplicationUuid();

        Http::assertSentCount(1);
    });

    it('clears cache when requested', function () {
        // This test verifies that clearCache() removes the correct cache keys
        $uniqueProjectUuid = 'clear-cache-test-uuid';

        config([
            'coolify.project_uuid' => $uniqueProjectUuid,
            'coolify.environment' => 'production',
            'coolify.cache_ttl' => 300,
        ]);

        // Pre-populate cache
        $cachePrefix = 'coolify:project:';
        Cache::put("{$cachePrefix}environment:{$uniqueProjectUuid}:production", ['cached' => true], 300);

        // Verify cache exists
        expect(Cache::has("{$cachePrefix}environment:{$uniqueProjectUuid}:production"))->toBeTrue();

        // Create service and clear cache
        $service = new \Stumason\Coolify\Services\CoolifyProjectService(
            app(\Stumason\Coolify\Contracts\ProjectRepository::class)
        );
        $service->clearCache();

        // Verify cache was cleared
        expect(Cache::has("{$cachePrefix}environment:{$uniqueProjectUuid}:production"))->toBeFalse();
    });

    it('returns resource summary', function () {
        config([
            'coolify.project_uuid' => 'test-project-uuid',
            'coolify.environment' => 'production',
            'coolify.application_uuid' => 'app-uuid',
            'coolify.database_uuid' => 'db-uuid',
            'coolify.redis_uuid' => 'redis-uuid',
            'coolify.server_uuid' => 'server-uuid',
        ]);

        Http::fake([
            '*/projects/test-project-uuid/production' => Http::response([
                'applications' => [
                    ['uuid' => 'app-uuid', 'name' => 'Test App', 'status' => 'running'],
                ],
                'postgresqls' => [
                    ['uuid' => 'db-uuid', 'name' => 'Test DB'],
                ],
            ], 200),
        ]);

        $service = app(CoolifyProjectService::class);
        $summary = $service->getResourceSummary();

        expect($summary)
            ->toHaveKey('project_uuid', 'test-project-uuid')
            ->toHaveKey('environment', 'production')
            ->toHaveKey('application_uuid', 'app-uuid')
            ->toHaveKey('database_uuid', 'db-uuid')
            ->toHaveKey('redis_uuid', 'redis-uuid')
            ->toHaveKey('server_uuid', 'server-uuid');
    });
});
