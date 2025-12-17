<?php

namespace Stumason\Coolify\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stumason\Coolify\Coolify;
use Stumason\Coolify\Exceptions\ForbiddenException;
use Symfony\Component\HttpFoundation\Response;

class Authenticate
{
    /**
     * Handle an incoming request.
     *
     * @throws ForbiddenException
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Coolify::check($request)) {
            throw new ForbiddenException('You are not authorized to access Coolify.');
        }

        return $next($request);
    }
}
