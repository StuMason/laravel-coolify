<?php

use Illuminate\Support\Facades\Http;
use Stumason\Coolify\Contracts\DatabaseRepository;

beforeEach(function () {
    Http::preventStrayRequests();
});

describe('DatabaseRepository', function () {
    it('fetches all databases', function () {
        Http::fake([
            '*/databases' => Http::response([
                ['uuid' => 'db-1', 'name' => 'postgres-1', 'type' => 'postgresql'],
                ['uuid' => 'db-2', 'name' => 'redis-1', 'type' => 'redis'],
            ], 200),
        ]);

        $dbs = app(DatabaseRepository::class)->all();

        expect($dbs)->toHaveCount(2)
            ->and($dbs[0]['type'])->toBe('postgresql');
    });

    it('fetches a single database', function () {
        Http::fake([
            '*/databases/db-123' => Http::response([
                'uuid' => 'db-123',
                'name' => 'my-postgres',
                'type' => 'postgresql',
                'status' => 'running',
            ], 200),
        ]);

        $db = app(DatabaseRepository::class)->get('db-123');

        expect($db['uuid'])->toBe('db-123')
            ->and($db['type'])->toBe('postgresql');
    });

    it('creates a PostgreSQL database', function () {
        Http::fake([
            '*/databases/postgresql' => Http::response([
                'uuid' => 'new-pg-uuid',
            ], 200),
        ]);

        $result = app(DatabaseRepository::class)->createPostgres([
            'name' => 'new-postgres',
            'server_uuid' => 'server-1',
        ]);

        expect($result['uuid'])->toBe('new-pg-uuid');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'databases/postgresql');
        });
    });

    it('creates a MySQL database', function () {
        Http::fake([
            '*/databases/mysql' => Http::response(['uuid' => 'mysql-uuid'], 200),
        ]);

        $result = app(DatabaseRepository::class)->createMysql([
            'name' => 'new-mysql',
        ]);

        expect($result['uuid'])->toBe('mysql-uuid');
    });

    it('creates a Dragonfly instance', function () {
        Http::fake([
            '*/databases/dragonfly' => Http::response(['uuid' => 'dragonfly-uuid'], 200),
        ]);

        $result = app(DatabaseRepository::class)->createDragonfly([
            'name' => 'new-dragonfly',
        ]);

        expect($result['uuid'])->toBe('dragonfly-uuid');
    });

    it('creates a Redis instance', function () {
        Http::fake([
            '*/databases/redis' => Http::response(['uuid' => 'redis-uuid'], 200),
        ]);

        $result = app(DatabaseRepository::class)->createRedis([
            'name' => 'new-redis',
        ]);

        expect($result['uuid'])->toBe('redis-uuid');
    });

    it('restarts a database', function () {
        Http::fake([
            '*/databases/db-123/restart' => Http::response(['success' => true], 200),
        ]);

        $result = app(DatabaseRepository::class)->restart('db-123');

        expect($result['success'])->toBeTrue();
    });

    it('lists backup schedules with executions', function () {
        Http::fake([
            '*/databases/db-123/backups' => Http::response([
                ['uuid' => 'schedule-1', 'frequency' => '0 0 * * *', 'enabled' => true],
                ['uuid' => 'schedule-2', 'frequency' => '0 12 * * *', 'enabled' => false],
            ], 200),
            '*/databases/db-123/backups/schedule-1/executions' => Http::response([
                ['uuid' => 'exec-1', 'status' => 'success'],
            ], 200),
            '*/databases/db-123/backups/schedule-2/executions' => Http::response([], 200),
        ]);

        $backups = app(DatabaseRepository::class)->backups('db-123');

        expect($backups)->toHaveCount(2)
            ->and($backups[0]['schedule']['uuid'])->toBe('schedule-1')
            ->and($backups[0]['executions'])->toHaveCount(1)
            ->and($backups[1]['schedule']['uuid'])->toBe('schedule-2')
            ->and($backups[1]['executions'])->toHaveCount(0);
    });

    it('returns empty array when no backup schedules', function () {
        Http::fake([
            '*/databases/db-123/backups' => Http::response([], 200),
        ]);

        $backups = app(DatabaseRepository::class)->backups('db-123');

        expect($backups)->toBeEmpty();
    });
});
