<?php

use Illuminate\Support\Facades\Http;

describe('RollbackCommand', function () {
    it('rolls back to a previous deployment', function () {
        Http::fake([
            '*/applications/test-app-uuid/deployments' => Http::response([
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
            ]),
            '*/applications/test-app-uuid/rollback' => Http::response([
                'message' => 'Rollback initiated',
                'deployment_uuid' => 'rollback-uuid',
            ]),
        ]);

        $this->artisan('coolify:rollback', ['--deployment' => 'deployment-2'])
            ->assertSuccessful();
    });

    it('shows available deployments when none specified', function () {
        Http::fake([
            '*/applications/test-app-uuid/deployments' => Http::response([
                [
                    'uuid' => 'deployment-1',
                    'deployment_uuid' => 'deployment-1',
                    'status' => 'finished',
                    'created_at' => '2024-01-01 10:00:00',
                ],
            ]),
        ]);

        // Without specifying a deployment, it should list options or fail gracefully
        $this->artisan('coolify:rollback')
            ->assertSuccessful();
    });
});
