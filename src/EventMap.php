<?php

namespace Stumason\Coolify;

trait EventMap
{
    /**
     * All of the Coolify event / listener mappings.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected array $events = [
        // Events\DeploymentStarted::class => [
        //     Listeners\NotifyDeploymentStarted::class,
        // ],
        // Events\DeploymentSucceeded::class => [
        //     Listeners\NotifyDeploymentSucceeded::class,
        // ],
        // Events\DeploymentFailed::class => [
        //     Listeners\NotifyDeploymentFailed::class,
        // ],
    ];
}
