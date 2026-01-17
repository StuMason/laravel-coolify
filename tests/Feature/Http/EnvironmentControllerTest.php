<?php

use Illuminate\Support\Facades\Http;
use Stumason\Coolify\Coolify;

beforeEach(function () {
    Http::preventStrayRequests();
    Coolify::auth(fn () => true);
});

describe('EnvironmentController', function () {
    it('lists environments from project', function () {
        Http::fake([
            '*/projects/test-project-uuid/environments' => Http::response([
                ['id' => 1, 'name' => 'production', 'description' => 'Production environment'],
                ['id' => 2, 'name' => 'staging', 'description' => 'Staging environment'],
            ], 200),
        ]);

        $response = $this->getJson(route('coolify.environments.index'));

        $response->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['name' => 'production', 'is_default' => true])
            ->assertJsonFragment(['name' => 'staging', 'is_default' => false]);
    });

    it('shows specific environment details', function () {
        Http::fake([
            '*/projects/test-project-uuid/staging' => Http::response([
                'name' => 'staging',
                'applications' => [
                    ['uuid' => 'app-1', 'name' => 'My Staging App'],
                ],
            ], 200),
        ]);

        $response = $this->getJson(route('coolify.environments.show', 'staging'));

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);
    });

    it('returns empty array when project not configured', function () {
        config(['coolify.project_uuid' => null]);

        $response = $this->getJson(route('coolify.environments.index'));

        $response->assertOk()
            ->assertJsonCount(0);
    });

    it('returns 404 for non-existent environment', function () {
        Http::fake([
            '*/projects/test-project-uuid/nonexistent' => Http::response(['message' => 'Not found'], 404),
        ]);

        $response = $this->getJson(route('coolify.environments.show', 'nonexistent'));

        $response->assertNotFound();
    });
});
