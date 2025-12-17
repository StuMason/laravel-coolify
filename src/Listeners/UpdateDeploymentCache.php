<?php

namespace Stumason\Coolify\Listeners;

use Illuminate\Support\Facades\Cache;
use Stumason\Coolify\Events\DeploymentFailed;
use Stumason\Coolify\Events\DeploymentStarted;
use Stumason\Coolify\Events\DeploymentSucceeded;

class UpdateDeploymentCache
{
    /**
     * Handle deployment events by clearing relevant caches.
     */
    public function handle(DeploymentStarted|DeploymentSucceeded|DeploymentFailed $event): void
    {
        $applicationUuid = $event->applicationUuid();

        // Clear application-specific cache
        Cache::forget("coolify:application:{$applicationUuid}");
        Cache::forget("coolify:application:{$applicationUuid}:deployments");

        // Clear general deployments cache
        Cache::forget('coolify:deployments:recent');
    }
}
