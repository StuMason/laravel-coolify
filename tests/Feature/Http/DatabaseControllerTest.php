<?php

use Illuminate\Support\Facades\Http;
use Stumason\Coolify\Coolify;

beforeEach(function () {
    Http::preventStrayRequests();
    Coolify::auth(fn () => true);
});

describe('DatabaseController', function () {
    it('lists all databases', function () {
        Http::fake([
            '*/databases' => Http::response([
                ['uuid' => 'db-1', 'name' => 'postgres-main', 'status' => 'running'],
                ['uuid' => 'db-2', 'name' => 'redis-cache', 'status' => 'running'],
            ], 200),
        ]);

        $response = $this->getJson(route('coolify.databases.index'));

        $response->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['name' => 'postgres-main']);
    });

    it('fetches database details', function () {
        Http::fake([
            '*/databases/db-123' => Http::response([
                'uuid' => 'db-123',
                'name' => 'my-database',
                'database_type' => 'postgresql',
                'status' => 'running',
                'internal_db_url' => 'postgres://user:pass@host:5432/db',
            ], 200),
        ]);

        $response = $this->getJson(route('coolify.databases.show', 'db-123'));

        $response->assertOk()
            ->assertJson([
                'uuid' => 'db-123',
                'name' => 'my-database',
            ]);
    });

    it('starts a database', function () {
        Http::fake([
            '*/databases/db-123/start' => Http::response(['success' => true], 200),
        ]);

        $response = $this->postJson(route('coolify.databases.start', 'db-123'));

        $response->assertOk();
    });

    it('stops a database', function () {
        Http::fake([
            '*/databases/db-123/stop' => Http::response(['success' => true], 200),
        ]);

        $response = $this->postJson(route('coolify.databases.stop', 'db-123'));

        $response->assertOk();
    });

    it('restarts a database', function () {
        Http::fake([
            '*/databases/db-123/restart' => Http::response(['success' => true], 200),
        ]);

        $response = $this->postJson(route('coolify.databases.restart', 'db-123'));

        $response->assertOk();
    });
});

describe('Database Backups', function () {
    it('lists backup schedules with executions', function () {
        Http::fake([
            '*/databases/db-123/backups' => Http::response([
                ['uuid' => 'schedule-1', 'frequency' => '0 0 * * *', 'enabled' => true, 'save_s3' => false],
            ], 200),
            '*/databases/db-123/backups/schedule-1/executions' => Http::response([
                ['uuid' => 'exec-1', 'status' => 'success', 'created_at' => '2024-01-15T10:00:00Z', 'size' => 1024000],
                ['uuid' => 'exec-2', 'status' => 'success', 'created_at' => '2024-01-14T10:00:00Z', 'size' => 1020000],
            ], 200),
        ]);

        $response = $this->getJson(route('coolify.databases.backups', 'db-123'));

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.schedule.uuid', 'schedule-1')
            ->assertJsonPath('0.executions.0.status', 'success');
    });

    it('returns empty array when no backup schedules configured', function () {
        Http::fake([
            '*/databases/db-123/backups' => Http::response([], 200),
        ]);

        $response = $this->getJson(route('coolify.databases.backups', 'db-123'));

        $response->assertOk()
            ->assertJsonCount(0);
    });
});
