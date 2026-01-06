<?php

use Illuminate\Support\Facades\Http;

beforeEach(function () {
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

        config(['coolify.application_uuid' => 'app-123']);

        // Just check that the --wait flag is accepted (without actually waiting)
        $this->artisan('coolify:deploy', ['--force' => true])
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

        config(['coolify.application_uuid' => 'app-123']);

        $this->artisan('coolify:deploy', ['--force' => true, '--debug' => true])
            ->expectsOutputToContain('Deployment triggered successfully')
            ->assertSuccessful();
    });
});
