<?php

use Illuminate\Support\Facades\Http;
use Stumason\Coolify\Coolify;

beforeEach(function () {
    Http::preventStrayRequests();
    Coolify::auth(fn () => true);
});

describe('EnvironmentController', function () {
    it('returns empty array for environments list', function () {
        // Multi-environment support has been removed
        // The endpoint now always returns an empty array
        $response = $this->getJson(route('coolify.environments.index'));

        $response->assertOk()
            ->assertJsonCount(0);
    });

    it('returns error when trying to switch environments', function () {
        // Multi-environment support has been removed
        // The switch endpoint now returns a 400 error
        $response = $this->postJson(route('coolify.environments.switch', 1));

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Multi-environment support has been removed. Configure COOLIFY_PROJECT_UUID in .env instead.',
            ]);
    });

    it('returns same error for any environment id', function () {
        // Any id should return the same error since multi-environment is removed
        $response = $this->postJson(route('coolify.environments.switch', 999));

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    });
});
