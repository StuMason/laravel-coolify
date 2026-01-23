<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
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
        // Create fake .git directory and mock git command for repository lookup
        $gitDir = base_path('.git');
        if (! is_dir($gitDir)) {
            mkdir($gitDir, 0755, true);
        }

        Process::fake([
            'git remote get-url origin*' => Process::result('git@github.com:StuMason/laravel-coolify.git'),
        ]);

        Http::fake([
            '*/version' => Http::response(['version' => '4.0'], 200),
            // Fake the applications list endpoint for git repository lookup
            '*/applications' => Http::response([
                [
                    'uuid' => 'test-app-uuid',
                    'name' => 'My App',
                    'git_repository' => 'https://github.com/StuMason/laravel-coolify',
                ],
            ], 200),
            '*/security/keys/test-deploy-key-uuid' => Http::response([
                'uuid' => 'test-deploy-key-uuid',
                'name' => 'test-key',
                'public_key' => 'ssh-ed25519 AAAA...',
            ], 200),
            '*/projects/test-project-uuid' => Http::response([
                'uuid' => 'test-project-uuid',
                'name' => 'Test Project',
                'environments' => [
                    ['uuid' => 'test-env-uuid', 'name' => 'production'],
                ],
            ], 200),
            // Environment endpoint for fetching resources
            '*/projects/test-project-uuid/production' => Http::response([
                'uuid' => 'test-env-uuid',
                'name' => 'production',
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
            '*/applications/test-app-uuid' => Http::response([
                'uuid' => 'test-app-uuid',
                'name' => 'My App',
                'status' => 'running',
                'fqdn' => 'https://myapp.com',
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

        // Cleanup
        if (is_dir($gitDir)) {
            rmdir($gitDir);
        }
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
