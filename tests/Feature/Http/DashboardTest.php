<?php

use Illuminate\Support\Facades\Http;
use Stumason\Coolify\Coolify;

beforeEach(function () {
    Http::preventStrayRequests();

    // Allow access to dashboard in tests
    Coolify::auth(fn () => true);
});

describe('Coolify Dashboard', function () {
    it('loads the dashboard', function () {
        $this->get(config('coolify.path'))
            ->assertOk()
            ->assertViewIs('coolify::spa');
    });

    it('returns stats from API endpoint', function () {
        Http::fake([
            '*/version' => Http::response(['version' => '4.0'], 200),
            '*/projects/test-project-uuid' => Http::response([
                'uuid' => 'test-project-uuid',
                'name' => 'Test Project',
                'environments' => [
                    ['uuid' => 'test-env-uuid', 'name' => 'production'],
                ],
            ], 200),
            // Environment endpoint for fetching resources (applications, databases)
            '*/projects/test-project-uuid/production' => Http::response([
                'uuid' => 'test-env-uuid',
                'name' => 'production',
                'applications' => [
                    [
                        'uuid' => 'test-app-uuid',
                        'name' => 'My App',
                        'status' => 'running',
                        'fqdn' => 'https://myapp.com',
                        'git_repository' => 'https://github.com/StuMason/laravel-coolify',
                        'git_branch' => 'main',
                    ],
                ],
                'postgresqls' => [
                    [
                        'uuid' => 'test-db-uuid',
                        'name' => 'test-db',
                        'database_type' => 'standalone-postgresql',
                        'status' => 'running',
                    ],
                ],
                'redis' => [
                    [
                        'uuid' => 'test-redis-uuid',
                        'name' => 'test-redis',
                        'status' => 'running',
                    ],
                ],
            ], 200),
            '*/deployments/applications/test-app-uuid' => Http::response([
                ['uuid' => 'deploy-1', 'status' => 'finished'],
            ], 200),
        ]);

        $response = $this->getJson(route('coolify.stats'));

        $response->assertOk()
            ->assertJson([
                'connected' => true,
                'application' => [
                    'name' => 'My App',
                    'status' => 'running',
                ],
            ]);
    });

    it('returns disconnected status on API failure', function () {
        Http::fake([
            '*' => Http::response(['message' => 'Error'], 500),
        ]);

        $response = $this->getJson(route('coolify.stats'));

        $response->assertOk()
            ->assertJson([
                'connected' => false,
            ]);
    });
});

describe('Dashboard Authentication', function () {
    it('blocks access when not authorized', function () {
        Coolify::auth(fn () => false);

        $this->get(config('coolify.path'))
            ->assertForbidden();
    });

    it('allows access in local environment by default', function () {
        Coolify::$authUsing = null; // Reset to default

        $this->app['env'] = 'local';

        $this->get(config('coolify.path'))
            ->assertOk();
    });
});
