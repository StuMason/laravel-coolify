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
            '*/deploy*' => Http::response([
                'deployments' => [[
                    'deployment_uuid' => 'deploy-456',
                    'message' => 'Deployment started',
                    'resource_uuid' => 'app-123',
                ]],
            ], 200),
        ]);

        $result = app(ApplicationRepository::class)->deploy('app-123');

        expect($result['deployment_uuid'])->toBe('deploy-456');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'deploy')
                && $request->method() === 'GET';
        });
    });

    it('deploys with force rebuild', function () {
        Http::fake([
            '*/deploy*' => Http::response([
                'deployments' => [[
                    'deployment_uuid' => 'deploy-force',
                    'message' => 'Force rebuild started',
                    'resource_uuid' => 'app-123',
                ]],
            ], 200),
        ]);

        $result = app(ApplicationRepository::class)->deploy('app-123', force: true);

        expect($result['deployment_uuid'])->toBe('deploy-force')
            ->and($result['force'])->toBeTrue();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'deploy')
                && str_contains($request->url(), 'force=true');
        });
    });

    it('deploys a specific commit', function () {
        Http::fake([
            '*/applications/app-123' => Http::response(['uuid' => 'app-123'], 200),
            '*/deploy*' => Http::response([
                'deployments' => [[
                    'deployment_uuid' => 'deploy-commit',
                    'message' => 'Commit deployment started',
                    'resource_uuid' => 'app-123',
                ]],
            ], 200),
        ]);

        $result = app(ApplicationRepository::class)->deploy('app-123', commit: 'abc123');

        expect($result['deployment_uuid'])->toBe('deploy-commit')
            ->and($result['commit'])->toBe('abc123');

        // Verify it first updates the app with the commit SHA
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'applications/app-123')
                && $request->method() === 'PATCH';
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

    it('creates a public application', function () {
        Http::fake([
            '*/applications/public' => Http::response([
                'uuid' => 'new-app-uuid',
            ], 200),
        ]);

        $result = app(ApplicationRepository::class)->createPublic([
            'name' => 'New App',
            'server_uuid' => 'server-1',
            'project_uuid' => 'project-1',
            'environment_name' => 'production',
            'git_repository' => 'https://github.com/user/repo',
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

    it('creates an environment variable', function () {
        Http::fake([
            '*/applications/app-123/envs' => Http::response([
                'uuid' => 'env-456',
                'key' => 'NEW_VAR',
                'value' => 'new_value',
            ], 200),
        ]);

        $result = app(ApplicationRepository::class)->createEnv('app-123', [
            'key' => 'NEW_VAR',
            'value' => 'new_value',
        ]);

        expect($result['uuid'])->toBe('env-456')
            ->and($result['key'])->toBe('NEW_VAR');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'applications/app-123/envs')
                && $request->method() === 'POST'
                && $request['key'] === 'NEW_VAR';
        });
    });

    it('updates an environment variable', function () {
        Http::fake([
            '*/applications/app-123/envs/env-456' => Http::response([
                'uuid' => 'env-456',
                'key' => 'UPDATED_VAR',
                'value' => 'updated_value',
            ], 200),
        ]);

        $result = app(ApplicationRepository::class)->updateEnv('app-123', 'env-456', [
            'key' => 'UPDATED_VAR',
            'value' => 'updated_value',
        ]);

        expect($result['uuid'])->toBe('env-456')
            ->and($result['key'])->toBe('UPDATED_VAR');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'applications/app-123/envs/env-456')
                && $request->method() === 'PATCH'
                && $request['key'] === 'UPDATED_VAR';
        });
    });

    it('deletes an environment variable', function () {
        Http::fake([
            '*/applications/app-123/envs/env-456' => Http::response([], 200),
        ]);

        $result = app(ApplicationRepository::class)->deleteEnv('app-123', 'env-456');

        expect($result)->toBeTrue();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'applications/app-123/envs/env-456')
                && $request->method() === 'DELETE';
        });
    });
});
