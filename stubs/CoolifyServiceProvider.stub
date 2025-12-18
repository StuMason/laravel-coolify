<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Stumason\Coolify\CoolifyApplicationServiceProvider;
use Stumason\Coolify\Coolify;

class CoolifyServiceProvider extends CoolifyApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Configure email notifications:
        // Coolify::routeMailNotificationsTo('devops@example.com');
    }

    /**
     * Register the Coolify gate.
     *
     * This gate determines who can access Coolify in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewCoolify', function ($user = null) {
            return in_array(optional($user)->email, [
                // Add authorized email addresses here:
                // 'admin@example.com',
            ]);
        });
    }
}
