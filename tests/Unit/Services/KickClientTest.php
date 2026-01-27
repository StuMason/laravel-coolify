<?php

use Illuminate\Support\Facades\Http;
use Stumason\Coolify\Exceptions\KickAuthenticationException;
use Stumason\Coolify\Exceptions\KickUnavailableException;
use Stumason\Coolify\Services\KickClient;

beforeEach(function () {
    Http::preventStrayRequests();
});

describe('KickClient', function () {
    it('fetches health status', function () {
        Http::fake([
            'https://example.com/kick/health' => Http::response([
                'status' => 'healthy',
                'checks' => [
                    'database' => ['status' => 'healthy', 'latency_ms' => 1.5],
                    'cache' => ['status' => 'healthy', 'latency_ms' => 0.8],
                ],
            ], 200),
        ]);

        $client = new KickClient('https://example.com/kick', 'test-token');
        $health = $client->health();

        expect($health['status'])->toBe('healthy')
            ->and($health['checks'])->toHaveKeys(['database', 'cache']);
    });

    it('fetches system stats', function () {
        Http::fake([
            'https://example.com/kick/stats' => Http::response([
                'stats' => [
                    'cpu' => ['load_average' => ['1m' => 0.5]],
                    'memory' => ['used_bytes' => 1024000],
                ],
            ], 200),
        ]);

        $client = new KickClient('https://example.com/kick', 'test-token');
        $stats = $client->stats();

        expect($stats['stats'])->toHaveKeys(['cpu', 'memory']);
    });

    it('fetches log files', function () {
        Http::fake([
            'https://example.com/kick/logs' => Http::response([
                'files' => [
                    ['name' => 'laravel.log', 'size' => 1024],
                    ['name' => 'worker.log', 'size' => 512],
                ],
            ], 200),
        ]);

        $client = new KickClient('https://example.com/kick', 'test-token');
        $logs = $client->logFiles();

        expect($logs['files'])->toHaveCount(2);
    });

    it('reads log entries with filters', function () {
        Http::fake([
            'https://example.com/kick/logs/*' => Http::response([
                'entries' => [
                    ['line' => 1, 'content' => '[ERROR] Something failed'],
                ],
                'total_lines' => 100,
            ], 200),
        ]);

        $client = new KickClient('https://example.com/kick', 'test-token');
        $result = $client->logRead('laravel.log', 'ERROR', 'failed', 50);

        expect($result['entries'])->toHaveCount(1);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'level=ERROR')
                && str_contains($request->url(), 'search=failed')
                && str_contains($request->url(), 'lines=50');
        });
    });

    it('fetches queue status', function () {
        Http::fake([
            'https://example.com/kick/queue' => Http::response([
                'connection' => 'redis',
                'queues' => ['default' => ['size' => 5]],
                'failed_count' => 2,
            ], 200),
        ]);

        $client = new KickClient('https://example.com/kick', 'test-token');
        $status = $client->queueStatus();

        expect($status['connection'])->toBe('redis')
            ->and($status['failed_count'])->toBe(2);
    });

    it('fetches failed jobs', function () {
        Http::fake([
            'https://example.com/kick/queue/failed*' => Http::response([
                'failed_jobs' => [
                    ['id' => 1, 'queue' => 'default', 'exception' => 'Error'],
                ],
            ], 200),
        ]);

        $client = new KickClient('https://example.com/kick', 'test-token');
        $result = $client->queueFailed(10);

        expect($result['failed_jobs'])->toHaveCount(1);
    });

    it('lists artisan commands', function () {
        Http::fake([
            'https://example.com/kick/artisan' => Http::response([
                'commands' => [
                    ['name' => 'cache:clear', 'description' => 'Clear cache'],
                    ['name' => 'config:cache', 'description' => 'Cache config'],
                ],
            ], 200),
        ]);

        $client = new KickClient('https://example.com/kick', 'test-token');
        $result = $client->artisanList();

        expect($result['commands'])->toHaveCount(2);
    });

    it('runs artisan command', function () {
        Http::fake([
            'https://example.com/kick/artisan' => Http::response([
                'success' => true,
                'command' => 'cache:clear',
                'output' => 'Cache cleared!',
                'exit_code' => 0,
            ], 200),
        ]);

        $client = new KickClient('https://example.com/kick', 'test-token');
        $result = $client->artisanRun('cache:clear');

        expect($result['success'])->toBeTrue()
            ->and($result['exit_code'])->toBe(0);

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && $request['command'] === 'cache:clear';
        });
    });

    it('checks reachability', function () {
        Http::fake([
            'https://example.com/kick/health' => Http::response(['status' => 'healthy'], 200),
        ]);

        $client = new KickClient('https://example.com/kick', 'test-token');

        expect($client->isReachable())->toBeTrue();
    });

    it('returns false for unreachable', function () {
        Http::fake([
            'https://example.com/kick/health' => Http::response([], 500),
        ]);

        $client = new KickClient('https://example.com/kick', 'test-token');

        expect($client->isReachable())->toBeFalse();
    });

    it('throws authentication exception on 401', function () {
        Http::fake([
            'https://example.com/kick/health' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $client = new KickClient('https://example.com/kick', 'test-token');

        expect(fn () => $client->health())->toThrow(KickAuthenticationException::class);
    });

    it('throws authentication exception on 403', function () {
        Http::fake([
            'https://example.com/kick/health' => Http::response(['error' => 'Forbidden'], 403),
        ]);

        $client = new KickClient('https://example.com/kick', 'test-token');

        expect(fn () => $client->health())->toThrow(KickAuthenticationException::class);
    });

    it('throws unavailable exception on 500', function () {
        Http::fake([
            'https://example.com/kick/health' => Http::response(['error' => 'Server error'], 500),
        ]);

        $client = new KickClient('https://example.com/kick', 'test-token');

        expect(fn () => $client->health())->toThrow(KickUnavailableException::class);
    });

    it('sends bearer token in requests', function () {
        Http::fake([
            'https://example.com/kick/health' => Http::response(['status' => 'healthy'], 200),
        ]);

        $client = new KickClient('https://example.com/kick', 'my-secret-token');
        $client->health();

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer my-secret-token');
        });
    });
});
