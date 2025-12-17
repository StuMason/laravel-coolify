<?php

namespace Stumason\Coolify\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Stumason\Coolify\CoolifyServiceProvider;

abstract class TestCase extends BaseTestCase
{
    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            CoolifyServiceProvider::class,
        ];
    }

    /**
     * Get package aliases.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return [
            'Coolify' => \Stumason\Coolify\Facades\Coolify::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('coolify.url', 'https://coolify.test');
        $app['config']->set('coolify.token', 'test-token');
        $app['config']->set('coolify.application_uuid', 'test-app-uuid');
        $app['config']->set('coolify.cache_ttl', 0);
    }
}
