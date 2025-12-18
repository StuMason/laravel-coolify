<?php

namespace Stumason\Coolify\Facades;

use Illuminate\Support\Facades\Facade;
use Stumason\Coolify\Contracts\ApplicationRepository;
use Stumason\Coolify\Contracts\DatabaseRepository;
use Stumason\Coolify\Contracts\DeploymentRepository;
use Stumason\Coolify\Contracts\ServerRepository;
use Stumason\Coolify\Contracts\ServiceRepository;

/**
 * @method static \Stumason\Coolify\Coolify auth(\Closure $callback)
 * @method static bool check(mixed $request)
 * @method static ApplicationRepository applications()
 * @method static DatabaseRepository databases()
 * @method static DeploymentRepository deployments()
 * @method static ServerRepository servers()
 * @method static ServiceRepository services()
 * @method static array deploy(?string $uuid = null)
 * @method static array status(?string $uuid = null)
 * @method static array logs(?string $uuid = null)
 * @method static \Stumason\Coolify\Coolify routeMailNotificationsTo(string $email)
 *
 * @see \Stumason\Coolify\Coolify
 */
class Coolify extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return \Stumason\Coolify\Coolify::class;
    }
}
