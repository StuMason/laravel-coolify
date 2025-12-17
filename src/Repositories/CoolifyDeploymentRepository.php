<?php

namespace Stumason\Coolify\Repositories;

use Stumason\Coolify\Contracts\DeploymentRepository;
use Stumason\Coolify\CoolifyClient;

class CoolifyDeploymentRepository implements DeploymentRepository
{
    public function __construct(
        protected CoolifyClient $client
    ) {}

    /**
     * @inheritDoc
     */
    public function all(): array
    {
        return $this->client->get('deployments');
    }

    /**
     * @inheritDoc
     */
    public function get(string $uuid): array
    {
        return $this->client->get("deployments/{$uuid}");
    }

    /**
     * @inheritDoc
     */
    public function forApplication(string $applicationUuid): array
    {
        return $this->client->get("applications/{$applicationUuid}/deployments");
    }

    /**
     * @inheritDoc
     */
    public function latest(string $applicationUuid): ?array
    {
        $deployments = $this->forApplication($applicationUuid);

        return $deployments[0] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function trigger(string $applicationUuid, array $options = []): array
    {
        return $this->client->post("applications/{$applicationUuid}/deploy", array_merge([
            'force' => false,
        ], $options));
    }

    /**
     * @inheritDoc
     */
    public function deployTag(string $applicationUuid, string $tag): array
    {
        return $this->client->post("deploy", [
            'uuid' => $applicationUuid,
            'tag' => $tag,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function cancel(string $uuid): array
    {
        return $this->client->post("deployments/{$uuid}/cancel");
    }

    /**
     * @inheritDoc
     */
    public function logs(string $uuid): array
    {
        return $this->client->get("deployments/{$uuid}/logs", cached: false);
    }

    /**
     * @inheritDoc
     */
    public function rollback(string $applicationUuid, string $deploymentUuid): array
    {
        return $this->client->post("applications/{$applicationUuid}/rollback", [
            'deployment_uuid' => $deploymentUuid,
        ]);
    }
}
