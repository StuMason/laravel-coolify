<?php

use Illuminate\Support\Facades\Http;
use Stumason\Coolify\Coolify;

describe('ServerController', function () {
    beforeEach(function () {
        Coolify::auth(fn () => true);
    });

    it('returns all servers', function () {
        Http::fake([
            '*/servers' => Http::response([
                ['uuid' => 'server-1', 'name' => 'Production', 'ip' => '192.168.1.1'],
                ['uuid' => 'server-2', 'name' => 'Staging', 'ip' => '192.168.1.2'],
            ]),
        ]);

        $response = $this->getJson('/coolify/api/servers');

        $response->assertOk()
            ->assertJsonCount(2);
    });

    it('returns a single server', function () {
        Http::fake([
            '*/servers/server-uuid-123' => Http::response([
                'uuid' => 'server-uuid-123',
                'name' => 'Production Server',
                'ip' => '192.168.1.1',
            ]),
        ]);

        $response = $this->getJson('/coolify/api/servers/server-uuid-123');

        $response->assertOk()
            ->assertJsonPath('uuid', 'server-uuid-123')
            ->assertJsonPath('name', 'Production Server');
    });

    it('returns server resources', function () {
        Http::fake([
            '*/servers/server-uuid-123/resources' => Http::response([
                'applications' => [['uuid' => 'app-1']],
                'databases' => [['uuid' => 'db-1']],
            ]),
        ]);

        $response = $this->getJson('/coolify/api/servers/server-uuid-123/resources');

        $response->assertOk()
            ->assertJsonStructure(['applications', 'databases']);
    });

    it('returns server domains', function () {
        Http::fake([
            '*/servers/server-uuid-123/domains' => Http::response([
                'example.com',
                'api.example.com',
            ]),
        ]);

        $response = $this->getJson('/coolify/api/servers/server-uuid-123/domains');

        $response->assertOk();
    });

    it('validates server connection', function () {
        Http::fake([
            '*/servers/server-uuid-123/validate' => Http::response([
                'valid' => true,
            ]),
        ]);

        $response = $this->postJson('/coolify/api/servers/server-uuid-123/validate');

        $response->assertOk();
    });

    it('requires authentication', function () {
        Coolify::auth(fn () => false);

        Http::fake([
            '*/servers' => Http::response([]),
        ]);

        $response = $this->getJson('/coolify/api/servers');

        $response->assertForbidden();
    });
});
