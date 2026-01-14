<?php

namespace Stumason\Coolify;

use Closure;
use Stumason\Coolify\Contracts\ApplicationRepository;
use Stumason\Coolify\Contracts\DatabaseRepository;
use Stumason\Coolify\Contracts\DeploymentRepository;
use Stumason\Coolify\Contracts\ProjectRepository;
use Stumason\Coolify\Contracts\ServerRepository;
use Stumason\Coolify\Contracts\ServiceRepository;
use Stumason\Coolify\Models\CoolifyResource;

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
     * Deploy the current application.
     */
    public static function deploy(?string $uuid = null): array
    {
        $uuid = $uuid ?? CoolifyResource::getDefault()?->application_uuid;

        return static::applications()->deploy($uuid);
    }

    /**
     * Get the status of the current application.
     */
    public static function status(?string $uuid = null): array
    {
        $uuid = $uuid ?? CoolifyResource::getDefault()?->application_uuid;

        return static::applications()->get($uuid);
    }

    /**
     * Get the logs for the current application.
     */
    public static function logs(?string $uuid = null): array
    {
        $uuid = $uuid ?? CoolifyResource::getDefault()?->application_uuid;

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
