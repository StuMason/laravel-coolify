<?php

namespace Stumason\Coolify;

use Stumason\Coolify\Events\ApplicationRestarted;
use Stumason\Coolify\Events\ApplicationStarted;
use Stumason\Coolify\Events\ApplicationStopped;
use Stumason\Coolify\Events\DeploymentFailed;
use Stumason\Coolify\Events\DeploymentStarted;
use Stumason\Coolify\Events\DeploymentSucceeded;
use Stumason\Coolify\Listeners\LogDeployment;
use Stumason\Coolify\Listeners\SendDeploymentNotification;
use Stumason\Coolify\Listeners\UpdateDeploymentCache;

trait EventMap
{
    /**
     * All of the Coolify event / listener mappings.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected array $events = [
        DeploymentStarted::class => [
            UpdateDeploymentCache::class,
        ],

        DeploymentSucceeded::class => [
            UpdateDeploymentCache::class,
        ],

        DeploymentFailed::class => [
            UpdateDeploymentCache::class,
        ],

        ApplicationStarted::class => [
            //
        ],

        ApplicationStopped::class => [
            //
        ],

        ApplicationRestarted::class => [
            //
        ],
    ];

    /**
     * All of the Coolify event subscribers.
     *
     * @var array<int, class-string>
     */
    protected array $subscribers = [
        LogDeployment::class,
        SendDeploymentNotification::class,
    ];
}
