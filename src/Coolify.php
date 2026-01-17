<?php

namespace Stumason\Coolify;

use Closure;
use Stumason\Coolify\Contracts\ApplicationRepository;
use Stumason\Coolify\Contracts\DatabaseRepository;
use Stumason\Coolify\Contracts\DeploymentRepository;
use Stumason\Coolify\Contracts\ProjectRepository;
use Stumason\Coolify\Contracts\ServerRepository;
use Stumason\Coolify\Contracts\ServiceRepository;
use Stumason\Coolify\Services\CoolifyProjectService;

class Coolify
{
    /**
     * The callback that should be used to authenticate Coolify dashboard users.
     */
    public static ?Closure $authUsing = null;

    /**
     * The email address for notifications.
     */
    public static ?string $email = null;

    /**
     * Indicates if Coolify routes have been registered.
     */
    public static bool $registeredRoutes = false;

    /**
     * Determine if the given request can access the Coolify dashboard.
     */
    public static function check(mixed $request): bool
    {
        return (static::$authUsing ?? function () {
            return app()->environment('local');
        })($request);
    }

    /**
     * Set the callback that should be used to authenticate Coolify dashboard users.
     */
    public static function auth(Closure $callback): self
    {
        static::$authUsing = $callback;

        return new self;
    }

    /**
     * Get the project service instance for resource discovery.
     */
    public static function project(): CoolifyProjectService
    {
        return app(CoolifyProjectService::class);
    }

    /**
     * Get the application repository instance.
     */
    public static function applications(): ApplicationRepository
    {
        return app(ApplicationRepository::class);
    }

    /**
     * Get the database repository instance.
     */
    public static function databases(): DatabaseRepository
    {
        return app(DatabaseRepository::class);
    }

    /**
     * Get the deployment repository instance.
     */
    public static function deployments(): DeploymentRepository
    {
        return app(DeploymentRepository::class);
    }

    /**
     * Get the server repository instance.
     */
    public static function servers(): ServerRepository
    {
        return app(ServerRepository::class);
    }

    /**
     * Get the service repository instance.
     */
    public static function services(): ServiceRepository
    {
        return app(ServiceRepository::class);
    }

    /**
     * Get the project repository instance.
     */
    public static function projects(): ProjectRepository
    {
        return app(ProjectRepository::class);
    }

    /**
     * Get the default application UUID.
     *
     * This uses the CoolifyProjectService to discover the application UUID
     * from COOLIFY_PROJECT_UUID or COOLIFY_APPLICATION_UUID config.
     */
    public static function applicationUuid(): ?string
    {
        return static::project()->getApplicationUuid();
    }

    /**
     * Get the default database UUID.
     */
    public static function databaseUuid(): ?string
    {
        return static::project()->getDatabaseUuid();
    }

    /**
     * Get the default Redis UUID.
     */
    public static function redisUuid(): ?string
    {
        return static::project()->getRedisUuid();
    }

    /**
     * Deploy the current application.
     */
    public static function deploy(?string $uuid = null): array
    {
        $uuid = $uuid ?? static::applicationUuid();

        return static::applications()->deploy($uuid);
    }

    /**
     * Get the status of the current application.
     */
    public static function status(?string $uuid = null): array
    {
        $uuid = $uuid ?? static::applicationUuid();

        return static::applications()->get($uuid);
    }

    /**
     * Get the logs for the current application.
     */
    public static function logs(?string $uuid = null): array
    {
        $uuid = $uuid ?? static::applicationUuid();

        return static::applications()->logs($uuid);
    }

    /**
     * Specify the email address to which notifications should be routed.
     */
    public static function routeMailNotificationsTo(string $email): self
    {
        static::$email = $email;

        return new self;
    }
}
