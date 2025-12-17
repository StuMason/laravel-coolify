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
    public function createPublic(array $data): array
    {
        return $this->client->post('applications/public', $data);
    }

    /**
     * @inheritDoc
     */
    public function createPrivateGithubApp(array $data): array
    {
        // App creation can take a while on Coolify - use 120s timeout
        return $this->client->post('applications/private-github-app', $data, timeout: 120);
    }

    /**
     * @inheritDoc
     */
    public function createPrivateDeployKey(array $data): array
    {
        return $this->client->post('applications/private-deploy-key', $data);
    }

    /**
     * @inheritDoc
     */
    public function createDockerfile(array $data): array
    {
        return $this->client->post('applications/dockerfile', $data);
    }

    /**
     * @inheritDoc
     */
    public function createDockerImage(array $data): array
    {
        return $this->client->post('applications/dockerimage', $data);
    }

    /**
     * @inheritDoc
     */
    public function createDockerCompose(array $data): array
    {
        return $this->client->post('applications/dockercompose', $data);
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
        // Coolify API uses POST /deploy with uuid in body
        $response = $this->client->post('deploy', [
            'uuid' => $uuid,
        ]);

        // API returns {deployments: [{message, resource_uuid, deployment_uuid}]}
        $deployment = $response['deployments'][0] ?? $response;

        return [
            'deployment_uuid' => $deployment['deployment_uuid'] ?? null,
            'message' => $deployment['message'] ?? null,
            'resource_uuid' => $deployment['resource_uuid'] ?? $uuid,
        ];
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
    public function createEnv(string $uuid, array $env): array
    {
        return $this->client->post("applications/{$uuid}/envs", $env);
    }

    /**
     * @inheritDoc
     */
    public function updateEnv(string $uuid, array $env): array
    {
        return $this->client->patch("applications/{$uuid}/envs", $env);
    }

    /**
     * @inheritDoc
     */
    public function deleteEnv(string $uuid, string $envUuid): bool
    {
        $this->client->delete("applications/{$uuid}/envs/{$envUuid}");

        return true;
    }
}
