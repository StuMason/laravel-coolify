<?php

use Illuminate\Support\Facades\Http;
use Stumason\Coolify\Contracts\ApplicationRepository;

beforeEach(function () {
    Http::preventStrayRequests();
});

describe('ApplicationRepository', function () {
    it('fetches all applications', function () {
        Http::fake([
            '*/applications' => Http::response([
                ['uuid' => 'app-1', 'name' => 'App 1'],
                ['uuid' => 'app-2', 'name' => 'App 2'],
            ], 200),
        ]);

        $apps = app(ApplicationRepository::class)->all();

        expect($apps)->toBeArray()
            ->and($apps)->toHaveCount(2)
            ->and($apps[0]['uuid'])->toBe('app-1');
    });

    it('fetches a single application', function () {
        Http::fake([
            '*/applications/app-123' => Http::response([
                'uuid' => 'app-123',
                'name' => 'My Application',
                'status' => 'running',
            ], 200),
        ]);

        $app = app(ApplicationRepository::class)->get('app-123');

        expect($app['uuid'])->toBe('app-123')
            ->and($app['name'])->toBe('My Application')
            ->and($app['status'])->toBe('running');
    });

    it('deploys an application', function () {
        Http::fake([
            '*/applications/app-123/deploy' => Http::response([
                'deployment_uuid' => 'deploy-456',
            ], 200),
        ]);

        $result = app(ApplicationRepository::class)->deploy('app-123');

        expect($result['deployment_uuid'])->toBe('deploy-456');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'deploy')
                && $request->method() === 'POST';
        });
    });

    it('restarts an application', function () {
        Http::fake([
            '*/applications/app-123/restart' => Http::response(['success' => true], 200),
        ]);

        $result = app(ApplicationRepository::class)->restart('app-123');

        expect($result['success'])->toBeTrue();
    });

    it('stops an application', function () {
        Http::fake([
            '*/applications/app-123/stop' => Http::response(['success' => true], 200),
        ]);

        $result = app(ApplicationRepository::class)->stop('app-123');

        expect($result['success'])->toBeTrue();
    });

    it('starts an application', function () {
        Http::fake([
            '*/applications/app-123/start' => Http::response(['success' => true], 200),
        ]);

        $result = app(ApplicationRepository::class)->start('app-123');

        expect($result['success'])->toBeTrue();
    });

    it('fetches application logs', function () {
        Http::fake([
            '*/applications/app-123/logs*' => Http::response([
                'logs' => "Line 1\nLine 2\nLine 3",
            ], 200),
        ]);

        $result = app(ApplicationRepository::class)->logs('app-123', 50);

        expect($result['logs'])->toContain('Line 1');
    });

    it('fetches environment variables', function () {
        Http::fake([
            '*/applications/app-123/envs' => Http::response([
                ['key' => 'APP_ENV', 'value' => 'production'],
                ['key' => 'DB_HOST', 'value' => 'localhost'],
            ], 200),
        ]);

        $envs = app(ApplicationRepository::class)->envs('app-123');

        expect($envs)->toHaveCount(2)
            ->and($envs[0]['key'])->toBe('APP_ENV');
    });

    it('creates an application', function () {
        Http::fake([
            '*/applications' => Http::response([
                'uuid' => 'new-app-uuid',
            ], 200),
        ]);

        $result = app(ApplicationRepository::class)->create([
            'name' => 'New App',
            'server_uuid' => 'server-1',
        ]);

        expect($result['uuid'])->toBe('new-app-uuid');
    });

    it('updates an application', function () {
        Http::fake([
            '*/applications/app-123' => Http::response([
                'uuid' => 'app-123',
                'name' => 'Updated Name',
            ], 200),
        ]);

        $result = app(ApplicationRepository::class)->update('app-123', [
            'name' => 'Updated Name',
        ]);

        expect($result['name'])->toBe('Updated Name');
    });

    it('deletes an application', function () {
        Http::fake([
            '*/applications/app-123' => Http::response([], 200),
        ]);

        $result = app(ApplicationRepository::class)->delete('app-123');

        expect($result)->toBeTrue();
    });
});
