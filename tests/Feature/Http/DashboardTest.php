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
            ->assertViewIs('coolify::dashboard');
    });

    it('returns stats from API endpoint', function () {
        Http::fake([
            '*/version' => Http::response(['version' => '4.0'], 200),
            '*/security/keys/test-deploy-key-uuid' => Http::response([
                'uuid' => 'test-deploy-key-uuid',
                'name' => 'test-key',
                'public_key' => 'ssh-ed25519 AAAA...',
            ], 200),
            '*/applications/test-app-uuid' => Http::response([
                'uuid' => 'test-app-uuid',
                'name' => 'My App',
                'status' => 'running',
                'fqdn' => 'https://myapp.com',
                'project' => ['uuid' => 'test-project-uuid'],
                'environment' => ['uuid' => 'test-env-uuid', 'name' => 'production'],
            ], 200),
            '*/applications/test-app-uuid/deployments' => Http::response([
                ['uuid' => 'deploy-1', 'status' => 'finished'],
            ], 200),
            '*/databases/test-db-uuid' => Http::response([
                'uuid' => 'test-db-uuid',
                'name' => 'test-db',
                'database_type' => 'postgresql',
                'status' => 'running',
            ], 200),
            '*/databases/test-redis-uuid' => Http::response([
                'uuid' => 'test-redis-uuid',
                'name' => 'test-redis',
                'database_type' => 'dragonfly',
                'status' => 'running',
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
