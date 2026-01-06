<?php

use Illuminate\Support\Facades\Http;
use Stumason\Coolify\Contracts\ApplicationRepository;
use Stumason\Coolify\Contracts\DatabaseRepository;
use Stumason\Coolify\Contracts\DeploymentRepository;
use Stumason\Coolify\Contracts\ServerRepository;
use Stumason\Coolify\Contracts\ServiceRepository;
use Stumason\Coolify\Coolify;

beforeEach(function () {
    Http::preventStrayRequests();
    Coolify::$authUsing = null;
});

describe('Coolify facade', function () {
    it('provides access to repositories', function () {
        expect(Coolify::applications())->toBeInstanceOf(ApplicationRepository::class)
            ->and(Coolify::databases())->toBeInstanceOf(DatabaseRepository::class)
            ->and(Coolify::deployments())->toBeInstanceOf(DeploymentRepository::class)
            ->and(Coolify::servers())->toBeInstanceOf(ServerRepository::class)
            ->and(Coolify::services())->toBeInstanceOf(ServiceRepository::class);
    });

    it('deploys using configured UUID', function () {
        Http::fake([
            '*/deploy' => Http::response([
                'deployments' => [[
                    'deployment_uuid' => 'deploy-123',
                    'message' => 'Deployment started',
                    'resource_uuid' => 'test-app-uuid',
                ]],
            ], 200),
        ]);

        $result = Coolify::deploy();

        expect($result['deployment_uuid'])->toBe('deploy-123');
    });

    it('deploys using custom UUID', function () {
        Http::fake([
            '*/deploy' => Http::response([
                'deployments' => [[
                    'deployment_uuid' => 'deploy-456',
                    'message' => 'Deployment started',
                    'resource_uuid' => 'custom-uuid',
                ]],
            ], 200),
        ]);

        $result = Coolify::deploy('custom-uuid');

        expect($result['deployment_uuid'])->toBe('deploy-456');
    });

    it('gets status using configured UUID', function () {
        Http::fake([
            '*/applications/test-app-uuid' => Http::response([
                'uuid' => 'test-app-uuid',
                'status' => 'running',
            ], 200),
        ]);

        $result = Coolify::status();

        expect($result['status'])->toBe('running');
    });

    it('gets logs using configured UUID', function () {
        Http::fake([
            '*/applications/test-app-uuid/logs*' => Http::response([
                'logs' => 'App logs...',
            ], 200),
        ]);

        $result = Coolify::logs();

        expect($result['logs'])->toBe('App logs...');
    });
});

describe('Coolify authentication', function () {
    it('allows access in local environment by default', function () {
        app()['env'] = 'local';

        expect(Coolify::check(request()))->toBeTrue();
    });

    it('denies access in production by default', function () {
        app()['env'] = 'production';

        expect(Coolify::check(request()))->toBeFalse();
    });

    it('uses custom auth callback', function () {
        Coolify::auth(fn ($request) => true);

        app()['env'] = 'production';

        expect(Coolify::check(request()))->toBeTrue();
    });

    it('returns self for fluent configuration', function () {
        $result = Coolify::auth(fn () => true);

        expect($result)->toBeInstanceOf(Coolify::class);
    });
});

describe('Coolify notifications', function () {
    it('configures email notifications', function () {
        Coolify::routeMailNotificationsTo('admin@example.com');

        expect(Coolify::$email)->toBe('admin@example.com');
    });
});
