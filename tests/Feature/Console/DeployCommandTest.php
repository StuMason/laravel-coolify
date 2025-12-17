<?php

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

describe('coolify:deploy command', function () {
    it('shows error when not configured', function () {
        config(['coolify.token' => null]);

        $this->artisan('coolify:deploy', ['--force' => true])
            ->assertFailed()
            ->expectsOutputToContain('not configured');
    });

    it('shows error when no application UUID', function () {
        config(['coolify.application_uuid' => null]);

        $this->artisan('coolify:deploy', ['--force' => true])
            ->assertFailed()
            ->expectsOutputToContain('No application UUID');
    });

    it('triggers deployment successfully', function () {
        Http::fake([
            '*/applications/test-app-uuid/deploy' => Http::response([
                'deployment_uuid' => 'new-deployment-123',
            ], 200),
        ]);

        $this->artisan('coolify:deploy', ['--force' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Deployment triggered successfully')
            ->expectsOutputToContain('new-deployment-123');
    });

    it('deploys specific tag', function () {
        Http::fake([
            '*/deploy' => Http::response([
                'deployment_uuid' => 'tag-deployment-123',
            ], 200),
        ]);

        $this->artisan('coolify:deploy', ['--force' => true, '--tag' => 'v1.0.0'])
            ->assertSuccessful()
            ->expectsOutputToContain('Deployment triggered');

        Http::assertSent(function ($request) {
            return $request['tag'] === 'v1.0.0';
        });
    });

    it('can use custom UUID', function () {
        Http::fake([
            '*/applications/custom-uuid/deploy' => Http::response([
                'deployment_uuid' => 'deploy-123',
            ], 200),
        ]);

        $this->artisan('coolify:deploy', ['--force' => true, '--uuid' => 'custom-uuid'])
            ->assertSuccessful();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'custom-uuid');
        });
    });
});
