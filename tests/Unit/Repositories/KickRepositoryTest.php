<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Stumason\Coolify\Contracts\KickRepository;

beforeEach(function () {
    Http::preventStrayRequests();
    Cache::flush();
});

describe('KickRepository', function () {
    it('returns null when kick is not configured', function () {
        Http::fake([
            '*/applications/*/envs' => Http::response([
                ['key' => 'APP_NAME', 'value' => 'Test App'],
            ], 200),
        ]);

        $config = app(KickRepository::class)->getConfig('app-123');

        expect($config)->toBeNull();
    });

    it('returns null when kick is disabled', function () {
        Http::fake([
            '*/applications/*/envs' => Http::response([
                ['key' => 'KICK_TOKEN', 'value' => 'secret-token'],
                ['key' => 'KICK_ENABLED', 'value' => 'false'],
            ], 200),
        ]);

        $config = app(KickRepository::class)->getConfig('app-123');

        expect($config)->toBeNull();
    });

    it('returns config when kick is enabled', function () {
        Http::fake([
            '*/applications/*/envs' => Http::response([
                ['key' => 'KICK_TOKEN', 'value' => 'secret-token'],
                ['key' => 'KICK_ENABLED', 'value' => 'true'],
            ], 200),
            '*/applications/app-123' => Http::response([
                'uuid' => 'app-123',
                'fqdn' => 'https://myapp.com',
            ], 200),
        ]);

        $config = app(KickRepository::class)->getConfig('app-123');

        expect($config)->toBeArray()
            ->and($config['base_url'])->toBe('https://myapp.com')
            ->and($config['token'])->toBe('secret-token')
            ->and($config['kick_path'])->toBe('kick');
    });

    it('uses custom kick prefix when configured', function () {
        Http::fake([
            '*/applications/*/envs' => Http::response([
                ['key' => 'KICK_TOKEN', 'value' => 'secret-token'],
                ['key' => 'KICK_ENABLED', 'value' => 'true'],
                ['key' => 'KICK_PREFIX', 'value' => 'api/kick'],
            ], 200),
            '*/applications/app-123' => Http::response([
                'uuid' => 'app-123',
                'fqdn' => 'https://myapp.com',
            ], 200),
        ]);

        $config = app(KickRepository::class)->getConfig('app-123');

        expect($config['kick_path'])->toBe('api/kick');
    });

    it('caches config lookups', function () {
        Http::fake([
            '*/applications/*/envs' => Http::response([
                ['key' => 'KICK_TOKEN', 'value' => 'secret-token'],
                ['key' => 'KICK_ENABLED', 'value' => 'true'],
            ], 200),
            '*/applications/app-123' => Http::response([
                'uuid' => 'app-123',
                'fqdn' => 'https://myapp.com',
            ], 200),
        ]);

        $repo = app(KickRepository::class);

        // First call
        $repo->getConfig('app-123');
        // Second call should use cache
        $repo->getConfig('app-123');

        // Should only have made one set of requests
        Http::assertSentCount(2); // envs + get app
    });

    it('checks reachability', function () {
        Http::fake([
            '*/applications/*/envs' => Http::response([
                ['key' => 'KICK_TOKEN', 'value' => 'secret-token'],
                ['key' => 'KICK_ENABLED', 'value' => 'true'],
            ], 200),
            '*/applications/app-123' => Http::response([
                'uuid' => 'app-123',
                'fqdn' => 'https://myapp.com',
            ], 200),
            'https://myapp.com/kick/health' => Http::response(['status' => 'healthy'], 200),
        ]);

        $reachable = app(KickRepository::class)->isReachable('app-123');

        expect($reachable)->toBeTrue();
    });

    it('returns false when not reachable', function () {
        Http::fake([
            '*/applications/*/envs' => Http::response([
                ['key' => 'KICK_TOKEN', 'value' => 'secret-token'],
                ['key' => 'KICK_ENABLED', 'value' => 'true'],
            ], 200),
            '*/applications/app-123' => Http::response([
                'uuid' => 'app-123',
                'fqdn' => 'https://myapp.com',
            ], 200),
            'https://myapp.com/kick/health' => Http::response([], 500),
        ]);

        $reachable = app(KickRepository::class)->isReachable('app-123');

        expect($reachable)->toBeFalse();
    });

    it('proxies health requests', function () {
        Http::fake([
            '*/applications/*/envs' => Http::response([
                ['key' => 'KICK_TOKEN', 'value' => 'token'],
                ['key' => 'KICK_ENABLED', 'value' => 'true'],
            ], 200),
            '*/applications/app-123' => Http::response([
                'uuid' => 'app-123',
                'fqdn' => 'https://myapp.com',
            ], 200),
            'https://myapp.com/kick/health' => Http::response([
                'status' => 'healthy',
                'checks' => ['database' => ['status' => 'healthy']],
            ], 200),
        ]);

        $health = app(KickRepository::class)->health('app-123');

        expect($health['status'])->toBe('healthy');
    });

    it('returns null when kick not configured for health', function () {
        Http::fake([
            '*/applications/*/envs' => Http::response([
                ['key' => 'APP_NAME', 'value' => 'Test'],
            ], 200),
        ]);

        $health = app(KickRepository::class)->health('app-123');

        expect($health)->toBeNull();
    });

    it('handles first fqdn when multiple are configured', function () {
        Http::fake([
            '*/applications/*/envs' => Http::response([
                ['key' => 'KICK_TOKEN', 'value' => 'token'],
                ['key' => 'KICK_ENABLED', 'value' => 'true'],
            ], 200),
            '*/applications/app-123' => Http::response([
                'uuid' => 'app-123',
                'fqdn' => 'https://primary.com,https://secondary.com',
            ], 200),
        ]);

        $config = app(KickRepository::class)->getConfig('app-123');

        expect($config['base_url'])->toBe('https://primary.com');
    });
});
