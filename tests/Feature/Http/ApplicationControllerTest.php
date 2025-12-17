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
            '*/applications/app-123/deploy' => Http::response([
                'deployment_uuid' => 'deploy-456',
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
