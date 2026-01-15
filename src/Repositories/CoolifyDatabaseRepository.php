<?php

declare(strict_types=1);

namespace Stumason\Coolify\Repositories;

use Stumason\Coolify\Contracts\DatabaseRepository;
use Stumason\Coolify\CoolifyClient;

class CoolifyDatabaseRepository implements DatabaseRepository
{
    public function __construct(
        protected CoolifyClient $client
    ) {}

    /**
     * {@inheritDoc}
     */
    public function all(): array
    {
        return $this->client->get('databases');
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $uuid): array
    {
        return $this->client->get("databases/{$uuid}");
    }

    /**
     * {@inheritDoc}
     */
    public function createPostgres(array $data): array
    {
        return $this->client->post('databases/postgresql', $data);
    }

    /**
     * {@inheritDoc}
     */
    public function createMysql(array $data): array
    {
        return $this->client->post('databases/mysql', $data);
    }

    /**
     * {@inheritDoc}
     */
    public function createMariadb(array $data): array
    {
        return $this->client->post('databases/mariadb', $data);
    }

    /**
     * {@inheritDoc}
     */
    public function createRedis(array $data): array
    {
        return $this->client->post('databases/redis', $data);
    }

    /**
     * {@inheritDoc}
     */
    public function createDragonfly(array $data): array
    {
        return $this->client->post('databases/dragonfly', $data);
    }

    /**
     * {@inheritDoc}
     */
    public function createMongodb(array $data): array
    {
        return $this->client->post('databases/mongodb', $data);
    }

    /**
     * {@inheritDoc}
     */
    public function update(string $uuid, array $data): array
    {
        return $this->client->patch("databases/{$uuid}", $data);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $uuid): bool
    {
        $this->client->delete("databases/{$uuid}");

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function start(string $uuid): array
    {
        return $this->client->post("databases/{$uuid}/start");
    }

    /**
     * {@inheritDoc}
     */
    public function stop(string $uuid): array
    {
        return $this->client->post("databases/{$uuid}/stop");
    }

    /**
     * {@inheritDoc}
     */
    public function restart(string $uuid): array
    {
        return $this->client->post("databases/{$uuid}/restart");
    }

    /**
     * {@inheritDoc}
     */
    public function backups(string $uuid): array
    {
        // Get backup schedules for the database
        $schedules = $this->client->get("databases/{$uuid}/backups");

        // For each schedule, get recent executions (handle individual failures gracefully)
        $result = [];
        foreach ($schedules as $schedule) {
            $executions = [];
            try {
                $response = $this->client->get("databases/{$uuid}/backups/{$schedule['uuid']}/executions");
                // API returns { "executions": [...] } - extract the array
                $executions = $response['executions'] ?? $response;
                if (! is_array($executions)) {
                    $executions = [];
                }
            } catch (\Exception $e) {
                // Execution fetch failed, but we still want to show the schedule
                $executions = [];
            }

            $result[] = [
                'schedule' => $schedule,
                'executions' => $executions,
            ];
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function createBackup(string $uuid, array $data): array
    {
        return $this->client->post("databases/{$uuid}/backups", $data);
    }

    /**
     * {@inheritDoc}
     */
    public function updateBackup(string $uuid, string $backupUuid, array $data): array
    {
        return $this->client->patch("databases/{$uuid}/backups/{$backupUuid}", $data);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteBackup(string $uuid, string $backupUuid): bool
    {
        $this->client->delete("databases/{$uuid}/backups/{$backupUuid}");

        return true;
    }
}
