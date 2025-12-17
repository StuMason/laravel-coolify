<?php

namespace Stumason\Coolify\Repositories;

use Stumason\Coolify\Contracts\ServiceRepository;
use Stumason\Coolify\CoolifyClient;

class CoolifyServiceRepository implements ServiceRepository
{
    public function __construct(
        protected CoolifyClient $client
    ) {}

    /**
     * @inheritDoc
     */
    public function all(): array
    {
        return $this->client->get('services');
    }

    /**
     * @inheritDoc
     */
    public function get(string $uuid): array
    {
        return $this->client->get("services/{$uuid}");
    }

    /**
     * @inheritDoc
     */
    public function create(array $data): array
    {
        return $this->client->post('services', $data);
    }

    /**
     * @inheritDoc
     */
    public function update(string $uuid, array $data): array
    {
        return $this->client->patch("services/{$uuid}", $data);
    }

    /**
     * @inheritDoc
     */
    public function delete(string $uuid): bool
    {
        $this->client->delete("services/{$uuid}");

        return true;
    }

    /**
     * @inheritDoc
     */
    public function start(string $uuid): array
    {
        return $this->client->post("services/{$uuid}/start");
    }

    /**
     * @inheritDoc
     */
    public function stop(string $uuid): array
    {
        return $this->client->post("services/{$uuid}/stop");
    }

    /**
     * @inheritDoc
     */
    public function restart(string $uuid): array
    {
        return $this->client->post("services/{$uuid}/restart");
    }
}
