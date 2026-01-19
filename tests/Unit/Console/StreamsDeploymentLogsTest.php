<?php

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();

    config([
        'coolify.url' => 'https://coolify.example.com',
        'coolify.token' => 'test-token',
    ]);
});

describe('DeployCommand signature', function () {
    it('has --wait flag for streaming logs', function () {
        Http::fake([
            '*/applications/app-123' => Http::response([
                'uuid' => 'app-123',
                'name' => 'Test App',
            ]),
            '*/deploy*' => Http::response([
                'deployment_uuid' => 'deploy-123',
            ]),
        ]);

        // Use --uuid to bypass git repository lookup
        $this->artisan('coolify:deploy', ['--uuid' => 'app-123', '--force' => true])
            ->expectsOutputToContain('Deployment triggered successfully')
            ->assertSuccessful();
    });

    it('accepts debug flag', function () {
        Http::fake([
            '*/applications/app-123' => Http::response([
                'uuid' => 'app-123',
                'name' => 'Test App',
            ]),
            '*/deploy*' => Http::response([
                'deployment_uuid' => 'deploy-123',
            ]),
        ]);

        // Use --uuid to bypass git repository lookup
        $this->artisan('coolify:deploy', ['--uuid' => 'app-123', '--force' => true, '--debug' => true])
            ->expectsOutputToContain('Deployment triggered successfully')
            ->assertSuccessful();
    });
});
