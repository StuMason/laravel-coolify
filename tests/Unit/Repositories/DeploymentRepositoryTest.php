<?php

use Illuminate\Support\Facades\Http;
use Stumason\Coolify\Contracts\DeploymentRepository;

beforeEach(function () {
    Http::preventStrayRequests();
});

describe('DeploymentRepository', function () {
    it('fetches all deployments', function () {
        Http::fake([
            '*/deployments' => Http::response([
                ['uuid' => 'deploy-1', 'status' => 'finished'],
                ['uuid' => 'deploy-2', 'status' => 'in_progress'],
            ], 200),
        ]);

        $deployments = app(DeploymentRepository::class)->all();

        expect($deployments)->toHaveCount(2)
            ->and($deployments[0]['status'])->toBe('finished');
    });

    it('fetches a single deployment', function () {
        Http::fake([
            '*/deployments/deploy-123' => Http::response([
                'uuid' => 'deploy-123',
                'status' => 'finished',
                'git_commit_sha' => 'abc123def456',
            ], 200),
        ]);

        $deployment = app(DeploymentRepository::class)->get('deploy-123');

        expect($deployment['uuid'])->toBe('deploy-123')
            ->and($deployment['status'])->toBe('finished');
    });

    it('fetches deployments for an application', function () {
        Http::fake([
            '*/deployments/applications/app-123' => Http::response([
                'deployments' => [
                    ['uuid' => 'deploy-1'],
                    ['uuid' => 'deploy-2'],
                ],
            ], 200),
        ]);

        $deployments = app(DeploymentRepository::class)->forApplication('app-123');

        expect($deployments)->toHaveCount(2);
    });

    it('gets latest deployment', function () {
        Http::fake([
            '*/deployments/applications/app-123' => Http::response([
                'deployments' => [
                    ['uuid' => 'deploy-latest', 'status' => 'finished'],
                    ['uuid' => 'deploy-old', 'status' => 'finished'],
                ],
            ], 200),
        ]);

        $latest = app(DeploymentRepository::class)->latest('app-123');

        expect($latest['uuid'])->toBe('deploy-latest');
    });

    it('returns null when no deployments', function () {
        Http::fake([
            '*/deployments/applications/app-123' => Http::response([
                'deployments' => [],
            ], 200),
        ]);

        $latest = app(DeploymentRepository::class)->latest('app-123');

        expect($latest)->toBeNull();
    });

    it('triggers a deployment', function () {
        Http::fake([
            '*/deploy*' => Http::response([
                'deployments' => [[
                    'deployment_uuid' => 'new-deploy-uuid',
                    'message' => 'Deployment started',
                    'resource_uuid' => 'app-123',
                ]],
            ], 200),
        ]);

        $result = app(DeploymentRepository::class)->trigger('app-123');

        expect($result['deployment_uuid'])->toBe('new-deploy-uuid');
    });

    it('deploys by tag', function () {
        Http::fake([
            '*/deploy*' => Http::response([
                'deployment_uuid' => 'tag-deploy-uuid',
            ], 200),
        ]);

        $result = app(DeploymentRepository::class)->deployTag('app-123', 'v1.0.0');

        expect($result['deployment_uuid'])->toBe('tag-deploy-uuid');

        Http::assertSent(function ($request) {
            return $request['uuid'] === 'app-123'
                && $request['tag'] === 'v1.0.0';
        });
    });

    it('cancels a deployment', function () {
        Http::fake([
            '*/deployments/deploy-123/cancel' => Http::response(['success' => true], 200),
        ]);

        $result = app(DeploymentRepository::class)->cancel('deploy-123');

        expect($result['success'])->toBeTrue();
    });

    it('fetches deployment logs', function () {
        Http::fake([
            '*/deployments/deploy-123' => Http::response([
                'uuid' => 'deploy-123',
                'status' => 'finished',
                'logs' => '[{"output": "Build step 1...", "type": "stdout"}]',
            ], 200),
        ]);

        $result = app(DeploymentRepository::class)->logs('deploy-123');

        expect($result)->toBeArray()
            ->and($result[0]['output'])->toContain('Build step');
    });
});
