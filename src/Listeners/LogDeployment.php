<?php

namespace Stumason\Coolify\Listeners;

use Illuminate\Support\Facades\Log;
use Stumason\Coolify\Events\DeploymentFailed;
use Stumason\Coolify\Events\DeploymentStarted;
use Stumason\Coolify\Events\DeploymentSucceeded;

class LogDeployment
{
    /**
     * Handle deployment started events.
     */
    public function handleStarted(DeploymentStarted $event): void
    {
        Log::channel(config('coolify.log_channel', 'stack'))->info(
            'Coolify deployment started',
            [
                'application' => $event->applicationName(),
                'application_uuid' => $event->applicationUuid(),
                'deployment_uuid' => $event->deploymentUuid(),
            ]
        );
    }

    /**
     * Handle deployment succeeded events.
     */
    public function handleSucceeded(DeploymentSucceeded $event): void
    {
        Log::channel(config('coolify.log_channel', 'stack'))->info(
            'Coolify deployment succeeded',
            [
                'application' => $event->applicationName(),
                'application_uuid' => $event->applicationUuid(),
                'deployment_uuid' => $event->deploymentUuid(),
                'duration_seconds' => $event->duration(),
            ]
        );
    }

    /**
     * Handle deployment failed events.
     */
    public function handleFailed(DeploymentFailed $event): void
    {
        Log::channel(config('coolify.log_channel', 'stack'))->error(
            'Coolify deployment failed',
            [
                'application' => $event->applicationName(),
                'application_uuid' => $event->applicationUuid(),
                'deployment_uuid' => $event->deploymentUuid(),
                'reason' => $event->reason(),
            ]
        );
    }

    /**
     * Register the listeners for the subscriber.
     *
     * @return array<class-string, string>
     */
    public function subscribe(): array
    {
        return [
            DeploymentStarted::class => 'handleStarted',
            DeploymentSucceeded::class => 'handleSucceeded',
            DeploymentFailed::class => 'handleFailed',
        ];
    }
}
