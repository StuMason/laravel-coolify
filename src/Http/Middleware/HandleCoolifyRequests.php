<?php

namespace Stumason\Coolify\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class HandleCoolifyRequests
{
    /**
     * Handle an incoming request.
     *
     * Override the Inertia root view and shared data for Coolify routes.
     * This prevents conflicts with the host application's Inertia configuration.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Set Coolify's root view (overrides host app's root view)
        Inertia::setRootView('coolify::app');

        // Clear any shared data from host app's middleware and set Coolify's own
        // This prevents errors from host app expecting auth.user.avatar etc.
        Inertia::share([
            // Remove any host app shared data that might cause issues
            'auth' => fn () => null,

            // Coolify specific data
            'coolify' => fn () => [
                'appName' => config('app.name'),
                'path' => config('coolify.path'),
                'polling' => config('coolify.polling_interval', 10),
            ],
        ]);

        return $next($request);
    }
}
