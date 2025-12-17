<?php

namespace Stumason\Coolify\Repositories;

use Stumason\Coolify\Contracts\ProjectRepository;
use Stumason\Coolify\CoolifyClient;

class CoolifyProjectRepository implements ProjectRepository
{
    public function __construct(
        protected CoolifyClient $client
    ) {}

    /**
     * @inheritDoc
     */
    public function all(): array
    {
        return $this->client->get('projects');
    }

    /**
     * @inheritDoc
     */
    public function get(string $uuid): array
    {
        return $this->client->get("projects/{$uuid}");
    }

    /**
     * @inheritDoc
     */
    public function create(array $data): array
    {
        return $this->client->post('projects', $data);
    }

    /**
     * @inheritDoc
     */
    public function update(string $uuid, array $data): array
    {
        return $this->client->patch("projects/{$uuid}", $data);
    }

    /**
     * @inheritDoc
     */
    public function delete(string $uuid): bool
    {
        $this->client->delete("projects/{$uuid}");

        return true;
    }

    /**
     * @inheritDoc
     */
    public function environments(string $uuid): array
    {
        return $this->client->get("projects/{$uuid}/environments");
    }

    /**
     * @inheritDoc
     */
    public function environment(string $projectUuid, string $environmentName): array
    {
        return $this->client->get("projects/{$projectUuid}/{$environmentName}");
    }
}
