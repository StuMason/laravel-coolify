<?php

namespace Stumason\Coolify\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Stumason\Coolify\CoolifyServiceProvider;
use Stumason\Coolify\Models\CoolifyResource;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

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
        // Set encryption key for testing
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        // Use SQLite in-memory database for testing
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Coolify configuration
        $app['config']->set('coolify.url', 'https://coolify.test');
        $app['config']->set('coolify.token', 'test-token');
        $app['config']->set('coolify.cache_ttl', 0);
    }

    /**
     * Define database migrations.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * Set up the test case.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create default test resource
        $this->createDefaultResource();
    }

    /**
     * Create a default CoolifyResource for testing.
     */
    protected function createDefaultResource(): CoolifyResource
    {
        return CoolifyResource::updateOrCreate(
            ['name' => 'test-app'],
            [
                'server_uuid' => 'test-server-uuid',
                'project_uuid' => 'test-project-uuid',
                'environment' => 'production',
                'deploy_key_uuid' => 'test-deploy-key-uuid',
                'repository' => 'test/repo',
                'branch' => 'main',
                'application_uuid' => 'test-app-uuid',
                'database_uuid' => 'test-db-uuid',
                'redis_uuid' => 'test-redis-uuid',
                'is_default' => true,
            ]
        );
    }
}
