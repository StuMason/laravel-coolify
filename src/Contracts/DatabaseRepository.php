<?php

namespace Stumason\Coolify\Contracts;

interface DatabaseRepository
{
    /**
     * Get all databases.
     *
     * @return array<string, mixed>
     */
    public function all(): array;

    /**
     * Get a database by UUID.
     *
     * @return array<string, mixed>
     */
    public function get(string $uuid): array;

    /**
     * Create a new PostgreSQL database.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createPostgres(array $data): array;

    /**
     * Create a new MySQL database.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createMysql(array $data): array;

    /**
     * Create a new MariaDB database.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createMariadb(array $data): array;

    /**
     * Create a new Redis instance.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createRedis(array $data): array;

    /**
     * Create a new Dragonfly instance (Redis-compatible).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createDragonfly(array $data): array;

    /**
     * Create a new MongoDB database.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createMongodb(array $data): array;

    /**
     * Update a database.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(string $uuid, array $data): array;

    /**
     * Delete a database.
     */
    public function delete(string $uuid): bool;

    /**
     * Start a database.
     *
     * @return array<string, mixed>
     */
    public function start(string $uuid): array;

    /**
     * Stop a database.
     *
     * @return array<string, mixed>
     */
    public function stop(string $uuid): array;

    /**
     * Restart a database.
     *
     * @return array<string, mixed>
     */
    public function restart(string $uuid): array;

    /**
     * Get backup schedules and their executions for the database.
     *
     * @return array<int, array<string, mixed>>
     */
    public function backups(string $uuid): array;

    /**
     * Create a backup schedule for the database.
     *
     * @param  array<string, mixed>  $data  Should contain 'frequency', 'enabled', 'save_s3', etc.
     * @return array<string, mixed>
     */
    public function createBackup(string $uuid, array $data): array;

    /**
     * Update a backup schedule.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateBackup(string $uuid, string $backupUuid, array $data): array;

    /**
     * Delete a backup schedule.
     */
    public function deleteBackup(string $uuid, string $backupUuid): bool;
}
