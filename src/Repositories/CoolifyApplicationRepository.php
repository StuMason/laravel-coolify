<?php

namespace Stumason\Coolify\Repositories;

use Stumason\Coolify\Contracts\ApplicationRepository;
use Stumason\Coolify\CoolifyClient;

class CoolifyApplicationRepository implements ApplicationRepository
{
    public function __construct(
        protected CoolifyClient $client
    ) {}

    /**
     * @inheritDoc
     */
    public function all(): array
    {
        return $this->client->get('applications');
    }

    /**
     * @inheritDoc
     */
    public function get(string $uuid): array
    {
        return $this->client->get("applications/{$uuid}");
    }

    /**
     * @inheritDoc
     */
    public function create(array $data): array
    {
        return $this->client->post('applications', $data);
    }

    /**
     * @inheritDoc
     */
    public function update(string $uuid, array $data): array
    {
        return $this->client->patch("applications/{$uuid}", $data);
    }

    /**
     * @inheritDoc
     */
    public function delete(string $uuid): bool
    {
        $this->client->delete("applications/{$uuid}");

        return true;
    }

    /**
     * @inheritDoc
     */
    public function deploy(string $uuid): array
    {
        return $this->client->post("applications/{$uuid}/deploy", [
            'force' => false,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function start(string $uuid): array
    {
        return $this->client->post("applications/{$uuid}/start");
    }

    /**
     * @inheritDoc
     */
    public function stop(string $uuid): array
    {
        return $this->client->post("applications/{$uuid}/stop");
    }

    /**
     * @inheritDoc
     */
    public function restart(string $uuid): array
    {
        return $this->client->post("applications/{$uuid}/restart");
    }

    /**
     * @inheritDoc
     */
    public function logs(string $uuid, int $lines = 100): array
    {
        return $this->client->get("applications/{$uuid}/logs", [
            'lines' => $lines,
        ], cached: false);
    }

    /**
     * @inheritDoc
     */
    public function envs(string $uuid): array
    {
        return $this->client->get("applications/{$uuid}/envs");
    }

    /**
     * @inheritDoc
     */
    public function updateEnvs(string $uuid, array $envs): array
    {
        return $this->client->patch("applications/{$uuid}/envs", $envs);
    }
}
