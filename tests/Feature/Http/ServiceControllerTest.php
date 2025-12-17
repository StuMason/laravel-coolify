<?php

use Illuminate\Support\Facades\Http;
use Stumason\Coolify\Coolify;

describe('ServiceController', function () {
    beforeEach(function () {
        Coolify::auth(fn () => true);
    });

    it('returns all services', function () {
        Http::fake([
            '*/services' => Http::response([
                ['uuid' => 'service-1', 'name' => 'Redis', 'type' => 'redis'],
                ['uuid' => 'service-2', 'name' => 'Minio', 'type' => 'minio'],
            ]),
        ]);

        $response = $this->getJson('/coolify/api/services');

        $response->assertOk()
            ->assertJsonCount(2);
    });

    it('returns a single service', function () {
        Http::fake([
            '*/services/service-uuid-123' => Http::response([
                'uuid' => 'service-uuid-123',
                'name' => 'Redis Service',
                'type' => 'redis',
            ]),
        ]);

        $response = $this->getJson('/coolify/api/services/service-uuid-123');

        $response->assertOk()
            ->assertJsonPath('uuid', 'service-uuid-123')
            ->assertJsonPath('name', 'Redis Service');
    });

    it('starts a service', function () {
        Http::fake([
            '*/services/service-uuid-123/start' => Http::response([
                'message' => 'Service started',
            ]),
        ]);

        $response = $this->postJson('/coolify/api/services/service-uuid-123/start');

        $response->assertOk();
    });

    it('stops a service', function () {
        Http::fake([
            '*/services/service-uuid-123/stop' => Http::response([
                'message' => 'Service stopped',
            ]),
        ]);

        $response = $this->postJson('/coolify/api/services/service-uuid-123/stop');

        $response->assertOk();
    });

    it('restarts a service', function () {
        Http::fake([
            '*/services/service-uuid-123/restart' => Http::response([
                'message' => 'Service restarted',
            ]),
        ]);

        $response = $this->postJson('/coolify/api/services/service-uuid-123/restart');

        $response->assertOk();
    });

    it('requires authentication', function () {
        Coolify::auth(fn () => false);

        Http::fake([
            '*/services' => Http::response([]),
        ]);

        $response = $this->getJson('/coolify/api/services');

        $response->assertForbidden();
    });
});
