<?php

use Illuminate\Support\Facades\Cache;
use Stumason\Coolify\Events\DeploymentFailed;
use Stumason\Coolify\Events\DeploymentStarted;
use Stumason\Coolify\Events\DeploymentSucceeded;
use Stumason\Coolify\Listeners\UpdateDeploymentCache;

describe('UpdateDeploymentCache', function () {
    beforeEach(function () {
        $this->listener = new UpdateDeploymentCache;

        $this->application = [
            'uuid' => 'app-uuid-123',
            'name' => 'Test Application',
        ];

        $this->deployment = [
            'uuid' => 'deployment-uuid-456',
            'deployment_uuid' => 'deployment-uuid-456',
            'status' => 'in_progress',
        ];
    });

    it('clears cache on deployment started', function () {
        Cache::shouldReceive('forget')
            ->once()
            ->with('coolify.applications')
            ->andReturn(true);

        Cache::shouldReceive('forget')
            ->once()
            ->with('coolify.application.app-uuid-123')
            ->andReturn(true);

        Cache::shouldReceive('forget')
            ->once()
            ->with('coolify.deployments.app-uuid-123')
            ->andReturn(true);

        $event = new DeploymentStarted($this->application, $this->deployment);

        $this->listener->handle($event);
    });

    it('clears cache on deployment succeeded', function () {
        Cache::shouldReceive('forget')
            ->times(3)
            ->andReturn(true);

        $event = new DeploymentSucceeded($this->application, $this->deployment);

        $this->listener->handle($event);
    });

    it('clears cache on deployment failed', function () {
        Cache::shouldReceive('forget')
            ->times(3)
            ->andReturn(true);

        $event = new DeploymentFailed($this->application, $this->deployment);

        $this->listener->handle($event);
    });
});
