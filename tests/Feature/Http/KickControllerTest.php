<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Stumason\Coolify\Coolify;

beforeEach(function () {
    Http::preventStrayRequests();
    Cache::flush();

    Coolify::auth(fn () => true);
    config([
        'coolify.enabled' => true,
        'coolify.kick.enabled' => true,
    ]);
});

function fakeKickConfigured(): void
{
    Http::fake([
        '*/applications/*/envs' => Http::response([
            ['key' => 'KICK_TOKEN', 'value' => 'test-token'],
            ['key' => 'KICK_ENABLED', 'value' => 'true'],
        ], 200),
        '*/applications/app-123' => Http::response([
            'uuid' => 'app-123',
            'fqdn' => 'https://myapp.com',
        ], 200),
    ]);
}

describe('KickController', function () {
    describe('status endpoint', function () {
        it('returns not configured when kick is missing', function () {
            Http::fake([
                '*/applications/*/envs' => Http::response([
                    ['key' => 'APP_NAME', 'value' => 'Test'],
                ], 200),
            ]);

            $response = $this->getJson('/coolify/api/kick/app-123/status');

            $response->assertOk()
                ->assertJson([
                    'available' => false,
                    'reason' => 'not_configured',
                ]);
        });

        it('returns available when kick is reachable', function () {
            fakeKickConfigured();
            Http::fake([
                'https://myapp.com/kick/health' => Http::response(['status' => 'healthy'], 200),
            ]);

            $response = $this->getJson('/coolify/api/kick/app-123/status');

            $response->assertOk()
                ->assertJson([
                    'available' => true,
                ]);
        });

        it('returns unreachable when kick endpoints fail', function () {
            fakeKickConfigured();
            Http::fake([
                'https://myapp.com/kick/health' => Http::response([], 500),
            ]);

            $response = $this->getJson('/coolify/api/kick/app-123/status');

            $response->assertOk()
                ->assertJson([
                    'available' => false,
                    'reason' => 'unreachable',
                ]);
        });
    });

    describe('health endpoint', function () {
        it('returns health data', function () {
            fakeKickConfigured();
            Http::fake([
                'https://myapp.com/kick/health' => Http::response([
                    'status' => 'healthy',
                    'checks' => [
                        'database' => ['status' => 'healthy', 'latency_ms' => 1.2],
                    ],
                ], 200),
            ]);

            $response = $this->getJson('/coolify/api/kick/app-123/health');

            $response->assertOk()
                ->assertJsonPath('status', 'healthy')
                ->assertJsonPath('checks.database.status', 'healthy');
        });

        it('returns 503 when kick unavailable', function () {
            Http::fake([
                '*/applications/*/envs' => Http::response([
                    ['key' => 'APP_NAME', 'value' => 'Test'],
                ], 200),
            ]);

            $response = $this->getJson('/coolify/api/kick/app-123/health');

            $response->assertStatus(503)
                ->assertJson(['error' => 'Kick not available']);
        });
    });

    describe('stats endpoint', function () {
        it('returns system stats', function () {
            fakeKickConfigured();
            Http::fake([
                'https://myapp.com/kick/stats' => Http::response([
                    'stats' => [
                        'cpu' => ['load_average' => ['1m' => 0.5]],
                        'memory' => ['used_bytes' => 1024000],
                    ],
                ], 200),
            ]);

            $response = $this->getJson('/coolify/api/kick/app-123/stats');

            $response->assertOk()
                ->assertJsonStructure(['stats' => ['cpu', 'memory']]);
        });
    });

    describe('logs endpoints', function () {
        it('lists log files', function () {
            fakeKickConfigured();
            Http::fake([
                'https://myapp.com/kick/logs' => Http::response([
                    'files' => [
                        ['name' => 'laravel.log', 'size' => 1024],
                    ],
                ], 200),
            ]);

            $response = $this->getJson('/coolify/api/kick/app-123/logs');

            $response->assertOk()
                ->assertJsonCount(1, 'files');
        });

        it('reads log entries with filters', function () {
            fakeKickConfigured();
            Http::fake([
                'https://myapp.com/kick/logs/*' => Http::response([
                    'entries' => [
                        ['line' => 1, 'content' => 'Error log entry'],
                    ],
                    'total_lines' => 100,
                ], 200),
            ]);

            $response = $this->getJson('/coolify/api/kick/app-123/logs/laravel.log?level=ERROR&search=error&lines=50');

            $response->assertOk()
                ->assertJsonCount(1, 'entries');
        });
    });

    describe('queue endpoints', function () {
        it('returns queue status', function () {
            fakeKickConfigured();
            Http::fake([
                'https://myapp.com/kick/queue' => Http::response([
                    'connection' => 'redis',
                    'queues' => ['default' => ['size' => 5]],
                    'failed_count' => 2,
                ], 200),
            ]);

            $response = $this->getJson('/coolify/api/kick/app-123/queue');

            $response->assertOk()
                ->assertJsonPath('connection', 'redis')
                ->assertJsonPath('failed_count', 2);
        });

        it('returns failed jobs', function () {
            fakeKickConfigured();
            Http::fake([
                'https://myapp.com/kick/queue/failed*' => Http::response([
                    'failed_jobs' => [
                        ['id' => 1, 'queue' => 'default'],
                    ],
                ], 200),
            ]);

            $response = $this->getJson('/coolify/api/kick/app-123/queue/failed?limit=10');

            $response->assertOk()
                ->assertJsonCount(1, 'failed_jobs');
        });
    });

    describe('artisan endpoints', function () {
        it('lists available commands', function () {
            fakeKickConfigured();
            Http::fake([
                'https://myapp.com/kick/artisan' => Http::response([
                    'commands' => [
                        ['name' => 'cache:clear', 'description' => 'Clear cache'],
                    ],
                ], 200),
            ]);

            $response = $this->getJson('/coolify/api/kick/app-123/artisan');

            $response->assertOk()
                ->assertJsonCount(1, 'commands');
        });

        it('executes artisan command', function () {
            fakeKickConfigured();
            Http::fake([
                'https://myapp.com/kick/artisan' => Http::response([
                    'success' => true,
                    'command' => 'cache:clear',
                    'output' => 'Cache cleared!',
                    'exit_code' => 0,
                ], 200),
            ]);

            $response = $this->postJson('/coolify/api/kick/app-123/artisan', [
                'command' => 'cache:clear',
            ]);

            $response->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('exit_code', 0);
        });

        it('validates command is required', function () {
            fakeKickConfigured();

            $response = $this->postJson('/coolify/api/kick/app-123/artisan', []);

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['command']);
        });
    });

    describe('authentication', function () {
        it('requires authentication when configured', function () {
            Coolify::auth(fn () => false);

            fakeKickConfigured();
            Http::fake([
                'https://myapp.com/kick/health' => Http::response(['status' => 'healthy'], 200),
            ]);

            $response = $this->getJson('/coolify/api/kick/app-123/health');

            $response->assertForbidden();
        });
    });
});
