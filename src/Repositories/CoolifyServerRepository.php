<?php

namespace Stumason\Coolify\Repositories;

use Stumason\Coolify\Contracts\ServerRepository;
use Stumason\Coolify\CoolifyClient;

class CoolifyServerRepository implements ServerRepository
{
    public function __construct(
        protected CoolifyClient $client
    ) {}

    /**
     * {@inheritDoc}
     */
    public function all(): array
    {
        return $this->client->get('servers');
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $uuid): array
    {
        return $this->client->get("servers/{$uuid}");
    }

    /**
     * {@inheritDoc}
     */
    public function create(array $data): array
    {
        return $this->client->post('servers', $data);
    }

    /**
     * {@inheritDoc}
     */
    public function update(string $uuid, array $data): array
    {
        return $this->client->patch("servers/{$uuid}", $data);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $uuid): bool
    {
        $this->client->delete("servers/{$uuid}");

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function resources(string $uuid): array
    {
        return $this->client->get("servers/{$uuid}/resources");
    }

    /**
     * {@inheritDoc}
     */
    public function domains(string $uuid): array
    {
        return $this->client->get("servers/{$uuid}/domains");
    }

    /**
     * {@inheritDoc}
     */
    public function validate(string $uuid): array
    {
        return $this->client->post("servers/{$uuid}/validate");
    }
}
