<?php

namespace Stumason\Coolify;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class CoolifyApplicationServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->authorization();
    }

    /**
     * Configure the Coolify authorization services.
     */
    protected function authorization(): void
    {
        $this->gate();

        Coolify::auth(function ($request) {
            return app()->environment('local')
                || Gate::check('viewCoolify', [$request->user()]);
        });
    }

    /**
     * Register the Coolify gate.
     *
     * This gate determines who can access Coolify in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewCoolify', function ($user = null) {
            return in_array($user?->email, [
                //
            ]);
        });
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }
}
