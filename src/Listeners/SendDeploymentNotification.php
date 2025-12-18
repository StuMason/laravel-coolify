<?php

namespace Stumason\Coolify\Listeners;

use Illuminate\Support\Facades\Notification;
use Stumason\Coolify\Coolify;
use Stumason\Coolify\Events\DeploymentFailed;
use Stumason\Coolify\Events\DeploymentStarted;
use Stumason\Coolify\Events\DeploymentSucceeded;
use Stumason\Coolify\Notifications\DeploymentFailed as DeploymentFailedNotification;
use Stumason\Coolify\Notifications\DeploymentStarted as DeploymentStartedNotification;
use Stumason\Coolify\Notifications\DeploymentSucceeded as DeploymentSucceededNotification;

class SendDeploymentNotification
{
    /**
     * Handle deployment started events.
     */
    public function handleStarted(DeploymentStarted $event): void
    {
        if (! $this->shouldNotify()) {
            return;
        }

        Notification::route('mail', Coolify::$email)
            ->notify(new DeploymentStartedNotification($event));
    }

    /**
     * Handle deployment succeeded events.
     */
    public function handleSucceeded(DeploymentSucceeded $event): void
    {
        if (! $this->shouldNotify()) {
            return;
        }

        Notification::route('mail', Coolify::$email)
            ->notify(new DeploymentSucceededNotification($event));
    }

    /**
     * Handle deployment failed events.
     */
    public function handleFailed(DeploymentFailed $event): void
    {
        if (! $this->shouldNotify()) {
            return;
        }

        Notification::route('mail', Coolify::$email)
            ->notify(new DeploymentFailedNotification($event));
    }

    /**
     * Determine if notifications should be sent.
     */
    protected function shouldNotify(): bool
    {
        return Coolify::$email !== null;
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
