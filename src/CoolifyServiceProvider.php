<?php

namespace Stumason\Coolify;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\CachesRoutes;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CoolifyServiceProvider extends ServiceProvider
{
    use EventMap;
    use ServiceBindings;

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerEvents();
        $this->registerRoutes();
        $this->registerResources();
        $this->registerCommands();
        $this->offerPublishing();
    }

    /**
     * Register the Coolify events and listeners.
     */
    protected function registerEvents(): void
    {
        $dispatcher = $this->app->make(Dispatcher::class);

        foreach ($this->events as $event => $listeners) {
            foreach ($listeners as $listener) {
                $dispatcher->listen($event, $listener);
            }
        }

        foreach ($this->subscribers as $subscriber) {
            $dispatcher->subscribe($subscriber);
        }
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (! defined('COOLIFY_PATH')) {
            define('COOLIFY_PATH', realpath(__DIR__.'/../'));
        }

        $this->configure();
        $this->registerServices();
    }

    /**
     * Setup the configuration for Coolify.
     */
    protected function configure(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/coolify.php',
            'coolify'
        );
    }

    /**
     * Register the Coolify routes.
     */
    protected function registerRoutes(): void
    {
        if ($this->app instanceof CachesRoutes && $this->app->routesAreCached()) {
            return;
        }

        Route::group([
            'domain' => config('coolify.domain'),
            'prefix' => config('coolify.path'),
            'namespace' => 'Stumason\Coolify\Http\Controllers',
            'middleware' => config('coolify.middleware', 'web'),
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });

        Coolify::$registeredRoutes = true;
    }

    /**
     * Register the Coolify resources.
     */
    protected function registerResources(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'coolify');
    }

    /**
     * Register the Coolify Artisan commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\DeployCommand::class,
                Console\DestroyCommand::class,
                Console\InstallCommand::class,
                Console\LogsCommand::class,
                Console\ProvisionCommand::class,
                Console\RestartCommand::class,
                Console\RollbackCommand::class,
                Console\SetupCiCommand::class,
                Console\StatusCommand::class,
            ]);
        }
    }

    /**
     * Register Coolify's services in the container.
     */
    protected function registerServices(): void
    {
        // Register the HTTP client
        $this->app->singleton(CoolifyClient::class, function ($app) {
            return new CoolifyClient(
                config('coolify.url'),
                config('coolify.token'),
                config('coolify.team_id')
            );
        });

        // Register repository bindings
        foreach ($this->serviceBindings as $key => $value) {
            is_numeric($key)
                ? $this->app->singleton($value)
                : $this->app->singleton($key, $value);
        }
    }

    /**
     * Setup the resource publishing groups for Coolify.
     */
    protected function offerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../stubs/CoolifyServiceProvider.stub' => app_path('Providers/CoolifyServiceProvider.php'),
            ], 'coolify-provider');

            $this->publishes([
                __DIR__.'/../config/coolify.php' => config_path('coolify.php'),
            ], 'coolify-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/coolify'),
            ], 'coolify-views');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'coolify-migrations');
        }
    }
}
