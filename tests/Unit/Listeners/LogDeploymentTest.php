<?php

use Illuminate\Support\Facades\Log;
use Stumason\Coolify\Events\DeploymentFailed;
use Stumason\Coolify\Events\DeploymentStarted;
use Stumason\Coolify\Events\DeploymentSucceeded;
use Stumason\Coolify\Listeners\LogDeployment;

describe('LogDeployment', function () {
    beforeEach(function () {
        $this->listener = new LogDeployment;

        $this->application = [
            'uuid' => 'app-uuid-123',
            'name' => 'Test Application',
        ];

        $this->deployment = [
            'uuid' => 'deployment-uuid-456',
            'deployment_uuid' => 'deployment-uuid-456',
            'status' => 'in_progress',
            'started_at' => '2024-01-01 10:00:00',
            'finished_at' => '2024-01-01 10:05:30',
        ];
    });

    it('logs deployment started event', function () {
        Log::shouldReceive('channel')
            ->once()
            ->with('stack')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->with('Coolify deployment started', \Mockery::type('array'));

        $event = new DeploymentStarted($this->application, $this->deployment);

        $this->listener->handleStarted($event);
    });

    it('logs deployment succeeded event', function () {
        Log::shouldReceive('channel')
            ->once()
            ->with('stack')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->with('Coolify deployment succeeded', \Mockery::type('array'));

        $event = new DeploymentSucceeded($this->application, $this->deployment);

        $this->listener->handleSucceeded($event);
    });

    it('logs deployment failed event', function () {
        $this->deployment['status_message'] = 'Build error';

        Log::shouldReceive('channel')
            ->once()
            ->with('stack')
            ->andReturnSelf();

        Log::shouldReceive('error')
            ->once()
            ->with('Coolify deployment failed', \Mockery::type('array'));

        $event = new DeploymentFailed($this->application, $this->deployment);

        $this->listener->handleFailed($event);
    });

    it('returns array of subscribed events', function () {
        $subscribedEvents = $this->listener->subscribe();

        expect($subscribedEvents)->toBeArray()
            ->and($subscribedEvents)->toHaveKey(DeploymentStarted::class)
            ->and($subscribedEvents)->toHaveKey(DeploymentSucceeded::class)
            ->and($subscribedEvents)->toHaveKey(DeploymentFailed::class);
    });
});
