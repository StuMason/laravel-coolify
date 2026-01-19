<?php

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

describe('RollbackCommand', function () {
    it('rolls back to a previous deployment', function () {
        Http::fake([
            '*/deployments/applications/test-app-uuid' => Http::response([
                'deployments' => [
                    [
                        'uuid' => 'deployment-1',
                        'deployment_uuid' => 'deployment-1',
                        'status' => 'finished',
                    ],
                    [
                        'uuid' => 'deployment-2',
                        'deployment_uuid' => 'deployment-2',
                        'status' => 'finished',
                    ],
                ],
            ]),
            '*/applications/test-app-uuid/rollback' => Http::response([
                'message' => 'Rollback initiated',
                'deployment_uuid' => 'rollback-uuid',
            ]),
        ]);

        // Use --uuid to bypass git repository lookup
        $this->artisan('coolify:rollback', ['--uuid' => 'test-app-uuid', '--deployment' => 'deployment-2', '--force' => true])
            ->assertSuccessful();
    });

    it('defaults to previous deployment in non-interactive mode', function () {
        Http::fake([
            '*/deployments/applications/test-app-uuid' => Http::response([
                'deployments' => [
                    [
                        'uuid' => 'deployment-current',
                        'deployment_uuid' => 'deployment-current',
                        'status' => 'finished',
                        'created_at' => '2024-01-02 10:00:00',
                    ],
                    [
                        'uuid' => 'deployment-previous',
                        'deployment_uuid' => 'deployment-previous',
                        'status' => 'finished',
                        'created_at' => '2024-01-01 10:00:00',
                    ],
                ],
            ]),
            '*/applications/test-app-uuid/rollback' => Http::response([
                'message' => 'Rollback initiated',
                'deployment_uuid' => 'rollback-uuid',
            ]),
        ]);

        // In non-interactive mode without specifying a deployment, it defaults to the previous one
        // Use --uuid to bypass git repository lookup
        $this->artisan('coolify:rollback', ['--uuid' => 'test-app-uuid', '--force' => true, '--no-interaction' => true])
            ->assertSuccessful();
    });
});
