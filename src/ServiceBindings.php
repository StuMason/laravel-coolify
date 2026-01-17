<?php

namespace Stumason\Coolify;

use Stumason\Coolify\Contracts\ApplicationRepository;
use Stumason\Coolify\Contracts\DatabaseRepository;
use Stumason\Coolify\Contracts\DeploymentRepository;
use Stumason\Coolify\Contracts\GitHubAppRepository;
use Stumason\Coolify\Contracts\ProjectRepository;
use Stumason\Coolify\Contracts\SecurityKeyRepository;
use Stumason\Coolify\Contracts\ServerRepository;
use Stumason\Coolify\Contracts\ServiceRepository;
use Stumason\Coolify\Contracts\TeamRepository;
use Stumason\Coolify\Repositories\CoolifyApplicationRepository;
use Stumason\Coolify\Repositories\CoolifyDatabaseRepository;
use Stumason\Coolify\Repositories\CoolifyDeploymentRepository;
use Stumason\Coolify\Repositories\CoolifyGitHubAppRepository;
use Stumason\Coolify\Repositories\CoolifyProjectRepository;
use Stumason\Coolify\Repositories\CoolifySecurityKeyRepository;
use Stumason\Coolify\Repositories\CoolifyServerRepository;
use Stumason\Coolify\Repositories\CoolifyServiceRepository;
use Stumason\Coolify\Repositories\CoolifyTeamRepository;
use Stumason\Coolify\Services\CoolifyProjectService;

trait ServiceBindings
{
    /**
     * All of the service bindings for Coolify.
     *
     * @var array<class-string, class-string>
     */
    public array $serviceBindings = [
        // Repository bindings
        ApplicationRepository::class => CoolifyApplicationRepository::class,
        DatabaseRepository::class => CoolifyDatabaseRepository::class,
        DeploymentRepository::class => CoolifyDeploymentRepository::class,
        GitHubAppRepository::class => CoolifyGitHubAppRepository::class,
        ProjectRepository::class => CoolifyProjectRepository::class,
        SecurityKeyRepository::class => CoolifySecurityKeyRepository::class,
        ServerRepository::class => CoolifyServerRepository::class,
        ServiceRepository::class => CoolifyServiceRepository::class,
        TeamRepository::class => CoolifyTeamRepository::class,

        // Service bindings
        CoolifyProjectService::class => CoolifyProjectService::class,
    ];
}
