<?php

use Illuminate\Support\Facades\Http;
use Stumason\Coolify\Coolify;
use Stumason\Coolify\Models\CoolifyResource;

beforeEach(function () {
    Http::preventStrayRequests();
    Coolify::auth(fn () => true);

    // Clean up any existing resources
    CoolifyResource::query()->delete();
});

describe('EnvironmentController', function () {
    it('lists all configured environments', function () {
        CoolifyResource::create([
            'name' => 'Production',
            'environment' => 'production',
            'server_uuid' => 'server-1',
            'project_uuid' => 'project-1',
            'application_uuid' => 'app-prod',
            'is_default' => true,
        ]);

        CoolifyResource::create([
            'name' => 'Staging',
            'environment' => 'staging',
            'server_uuid' => 'server-1',
            'project_uuid' => 'project-1',
            'application_uuid' => 'app-staging',
            'is_default' => false,
        ]);

        $response = $this->getJson(route('coolify.environments.index'));

        $response->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['name' => 'Production', 'is_default' => true])
            ->assertJsonFragment(['name' => 'Staging', 'is_default' => false]);
    });

    it('switches to a different environment', function () {
        $prod = CoolifyResource::create([
            'name' => 'Production',
            'environment' => 'production',
            'server_uuid' => 'server-1',
            'project_uuid' => 'project-1',
            'application_uuid' => 'app-prod',
            'is_default' => true,
        ]);

        $staging = CoolifyResource::create([
            'name' => 'Staging',
            'environment' => 'staging',
            'server_uuid' => 'server-1',
            'project_uuid' => 'project-1',
            'application_uuid' => 'app-staging',
            'is_default' => false,
        ]);

        $response = $this->postJson(route('coolify.environments.switch', $staging->id));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Switched to Staging',
            ]);

        // Verify the switch happened
        expect(CoolifyResource::find($staging->id)->is_default)->toBeTrue();
        expect(CoolifyResource::find($prod->id)->is_default)->toBeFalse();
    });

    it('returns 404 for non-existent environment', function () {
        $response = $this->postJson(route('coolify.environments.switch', 999));

        $response->assertNotFound();
    });

    it('returns empty array when no environments configured', function () {
        $response = $this->getJson(route('coolify.environments.index'));

        $response->assertOk()
            ->assertJsonCount(0);
    });
});
