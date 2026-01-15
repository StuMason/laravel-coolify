<?php

use Illuminate\Support\Facades\Http;
use Stumason\Coolify\Coolify;

beforeEach(function () {
    Http::preventStrayRequests();
    Coolify::auth(fn () => true);
});

describe('ApplicationController', function () {
    it('fetches application details', function () {
        Http::fake([
            '*/applications/app-123' => Http::response([
                'uuid' => 'app-123',
                'name' => 'My App',
                'status' => 'running',
            ], 200),
        ]);

        $response = $this->getJson(route('coolify.applications.show', 'app-123'));

        $response->assertOk()
            ->assertJson([
                'uuid' => 'app-123',
                'name' => 'My App',
            ]);
    });

    it('triggers deployment', function () {
        Http::fake([
            '*/deploy' => Http::response([
                'deployments' => [[
                    'deployment_uuid' => 'deploy-456',
                    'message' => 'Deployment started',
                    'resource_uuid' => 'app-123',
                ]],
            ], 200),
        ]);

        $response = $this->postJson(route('coolify.applications.deploy', 'app-123'));

        $response->assertOk()
            ->assertJson([
                'deployment_uuid' => 'deploy-456',
            ]);
    });

    it('restarts application', function () {
        Http::fake([
            '*/applications/app-123/restart' => Http::response(['success' => true], 200),
        ]);

        $response = $this->postJson(route('coolify.applications.restart', 'app-123'));

        $response->assertOk();
    });

    it('stops application', function () {
        Http::fake([
            '*/applications/app-123/stop' => Http::response(['success' => true], 200),
        ]);

        $response = $this->postJson(route('coolify.applications.stop', 'app-123'));

        $response->assertOk();
    });

    it('starts application', function () {
        Http::fake([
            '*/applications/app-123/start' => Http::response(['success' => true], 200),
        ]);

        $response = $this->postJson(route('coolify.applications.start', 'app-123'));

        $response->assertOk();
    });

    it('fetches application logs', function () {
        Http::fake([
            '*/applications/app-123/logs*' => Http::response([
                'logs' => 'Application log output...',
            ], 200),
        ]);

        $response = $this->getJson(route('coolify.applications.logs', 'app-123'));

        $response->assertOk()
            ->assertJson([
                'logs' => 'Application log output...',
            ]);
    });

    it('handles API errors gracefully', function () {
        Http::fake([
            '*' => Http::response(['message' => 'Not found'], 404),
        ]);

        $response = $this->getJson(route('coolify.applications.show', 'not-found'));

        $response->assertStatus(404)
            ->assertJson([
                'error' => 'The requested resource was not found in Coolify.',
            ]);
    });
});

describe('Environment Variables', function () {
    it('lists environment variables', function () {
        Http::fake([
            '*/applications/app-123/envs' => Http::response([
                ['uuid' => 'env-1', 'key' => 'APP_NAME', 'value' => 'MyApp', 'is_build_time' => false],
                ['uuid' => 'env-2', 'key' => 'DB_PASSWORD', 'value' => 'secret123', 'is_build_time' => false],
            ], 200),
        ]);

        $response = $this->getJson(route('coolify.applications.envs', 'app-123'));

        $response->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['key' => 'APP_NAME'])
            ->assertJsonFragment(['key' => 'DB_PASSWORD']);
    });

    it('creates environment variable', function () {
        Http::fake([
            '*/applications/app-123/envs' => Http::response([
                'uuid' => 'env-new',
                'key' => 'NEW_VAR',
                'value' => 'new_value',
            ], 201),
        ]);

        $response = $this->postJson(route('coolify.applications.envs.create', 'app-123'), [
            'key' => 'NEW_VAR',
            'value' => 'new_value',
            'is_build_time' => false,
        ]);

        $response->assertOk()
            ->assertJsonFragment(['key' => 'NEW_VAR']);
    });

    it('updates environment variable', function () {
        Http::fake([
            '*/applications/app-123/envs' => Http::response([
                'uuid' => 'env-1',
                'key' => 'APP_NAME',
                'value' => 'UpdatedApp',
            ], 200),
        ]);

        $response = $this->patchJson(route('coolify.applications.envs.update', ['uuid' => 'app-123', 'envUuid' => 'env-1']), [
            'key' => 'APP_NAME',
            'value' => 'UpdatedApp',
        ]);

        $response->assertOk();
    });

    it('deletes environment variable', function () {
        Http::fake([
            '*/applications/app-123/envs/env-1' => Http::response(null, 204),
        ]);

        $response = $this->deleteJson(route('coolify.applications.envs.delete', ['uuid' => 'app-123', 'envUuid' => 'env-1']));

        $response->assertOk()
            ->assertJson(['success' => true]);
    });
});
