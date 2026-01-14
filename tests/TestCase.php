<?php

namespace Stumason\Coolify\Tests;

use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Stumason\Coolify\CoolifyServiceProvider;
use Stumason\Coolify\Models\CoolifyResource;

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
     * Set up the test case.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create table if not exists
        if (! Schema::hasTable('coolify_resources')) {
            Schema::create('coolify_resources', function ($table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('server_uuid');
                $table->string('project_uuid');
                $table->string('environment')->default('production');
                $table->string('deploy_key_uuid')->nullable();
                $table->string('repository')->nullable();
                $table->string('branch')->nullable();
                $table->string('application_uuid')->nullable();
                $table->string('database_uuid')->nullable();
                $table->string('redis_uuid')->nullable();
                $table->boolean('is_default')->default(false)->index();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

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
