<?php

use Illuminate\Support\Facades\Http;
use Stumason\Coolify\Contracts\ServerRepository;

describe('ServerRepository', function () {
    beforeEach(function () {
        $this->repository = app(ServerRepository::class);
    });

    it('lists all servers', function () {
        Http::fake([
            '*/servers' => Http::response([
                ['uuid' => 'server-1', 'name' => 'Production', 'ip' => '192.168.1.1', 'is_reachable' => true],
                ['uuid' => 'server-2', 'name' => 'Staging', 'ip' => '192.168.1.2', 'is_reachable' => false],
            ]),
        ]);

        $servers = $this->repository->all();

        expect($servers)->toBeArray()
            ->and($servers)->toHaveCount(2)
            ->and($servers[0]['name'])->toBe('Production');
    });

    it('gets a server by uuid', function () {
        Http::fake([
            '*/servers/server-uuid-123' => Http::response([
                'uuid' => 'server-uuid-123',
                'name' => 'Production Server',
                'ip' => '192.168.1.1',
                'is_reachable' => true,
                'is_usable' => true,
            ]),
        ]);

        $server = $this->repository->get('server-uuid-123');

        expect($server)->toBeArray()
            ->and($server['uuid'])->toBe('server-uuid-123')
            ->and($server['name'])->toBe('Production Server');
    });

    it('gets server resources', function () {
        Http::fake([
            '*/servers/server-uuid-123/resources' => Http::response([
                'applications' => [
                    ['uuid' => 'app-1', 'name' => 'App One'],
                ],
                'databases' => [
                    ['uuid' => 'db-1', 'name' => 'DB One'],
                ],
            ]),
        ]);

        $resources = $this->repository->resources('server-uuid-123');

        expect($resources)->toBeArray()
            ->and($resources)->toHaveKey('applications')
            ->and($resources)->toHaveKey('databases');
    });

    it('gets server domains', function () {
        Http::fake([
            '*/servers/server-uuid-123/domains' => Http::response([
                'example.com',
                'api.example.com',
            ]),
        ]);

        $domains = $this->repository->domains('server-uuid-123');

        expect($domains)->toBeArray()
            ->and($domains)->toContain('example.com');
    });

    it('validates server connection', function () {
        Http::fake([
            '*/servers/server-uuid-123/validate' => Http::response([
                'valid' => true,
            ]),
        ]);

        $result = $this->repository->validate('server-uuid-123');

        expect($result)->toBeArray()
            ->and($result['valid'])->toBeTrue();
    });
});
