<?php

namespace Stumason\Coolify\Repositories;

use Stumason\Coolify\Contracts\DatabaseRepository;
use Stumason\Coolify\CoolifyClient;

class CoolifyDatabaseRepository implements DatabaseRepository
{
    public function __construct(
        protected CoolifyClient $client
    ) {}

    /**
     * @inheritDoc
     */
    public function all(): array
    {
        return $this->client->get('databases');
    }

    /**
     * @inheritDoc
     */
    public function get(string $uuid): array
    {
        return $this->client->get("databases/{$uuid}");
    }

    /**
     * @inheritDoc
     */
    public function createPostgres(array $data): array
    {
        return $this->client->post('databases/postgresql', $data);
    }

    /**
     * @inheritDoc
     */
    public function createMysql(array $data): array
    {
        return $this->client->post('databases/mysql', $data);
    }

    /**
     * @inheritDoc
     */
    public function createMariadb(array $data): array
    {
        return $this->client->post('databases/mariadb', $data);
    }

    /**
     * @inheritDoc
     */
    public function createRedis(array $data): array
    {
        return $this->client->post('databases/redis', $data);
    }

    /**
     * @inheritDoc
     */
    public function createDragonfly(array $data): array
    {
        return $this->client->post('databases/dragonfly', $data);
    }

    /**
     * @inheritDoc
     */
    public function createMongodb(array $data): array
    {
        return $this->client->post('databases/mongodb', $data);
    }

    /**
     * @inheritDoc
     */
    public function update(string $uuid, array $data): array
    {
        return $this->client->patch("databases/{$uuid}", $data);
    }

    /**
     * @inheritDoc
     */
    public function delete(string $uuid): bool
    {
        $this->client->delete("databases/{$uuid}");

        return true;
    }

    /**
     * @inheritDoc
     */
    public function start(string $uuid): array
    {
        return $this->client->post("databases/{$uuid}/start");
    }

    /**
     * @inheritDoc
     */
    public function stop(string $uuid): array
    {
        return $this->client->post("databases/{$uuid}/stop");
    }

    /**
     * @inheritDoc
     */
    public function restart(string $uuid): array
    {
        return $this->client->post("databases/{$uuid}/restart");
    }

    /**
     * @inheritDoc
     */
    public function backup(string $uuid): array
    {
        return $this->client->post("databases/{$uuid}/backup");
    }

    /**
     * @inheritDoc
     */
    public function backups(string $uuid): array
    {
        return $this->client->get("databases/{$uuid}/backups");
    }
}
