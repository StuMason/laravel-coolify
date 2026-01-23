<?php

use Illuminate\Support\Facades\Http;
use Stumason\Coolify\Coolify;

beforeEach(function () {
    Http::preventStrayRequests();
    Coolify::auth(fn () => true);
});

describe('EnvironmentController', function () {
    it('returns environments from the project', function () {
        Http::fake([
            '*/projects/test-project-uuid' => Http::response([
                'uuid' => 'test-project-uuid',
                'name' => 'Test Project',
                'environments' => [
                    ['uuid' => 'env-1', 'name' => 'production'],
                    ['uuid' => 'env-2', 'name' => 'staging'],
                ],
            ], 200),
        ]);

        $response = $this->getJson(route('coolify.environments.index'));

        $response->assertOk()
            ->assertJsonCount(2)
            ->assertJson([
                ['uuid' => 'env-1', 'name' => 'production'],
                ['uuid' => 'env-2', 'name' => 'staging'],
            ]);
    });

    it('returns empty array when no project configured', function () {
        config(['coolify.project_uuid' => null]);

        $response = $this->getJson(route('coolify.environments.index'));

        $response->assertOk()
            ->assertJsonCount(0);
    });

    it('returns empty array when project not found', function () {
        Http::fake([
            '*/projects/test-project-uuid' => Http::response(['message' => 'Not found'], 404),
        ]);

        $response = $this->getJson(route('coolify.environments.index'));

        $response->assertOk()
            ->assertJsonCount(0);
    });
});
