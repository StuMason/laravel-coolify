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
        // Coolify API endpoint is /deployments/applications/{uuid}, not /applications/{uuid}/deployments
        $response = $this->client->get("deployments/applications/{$applicationUuid}");

        return $response['deployments'] ?? [];
    }

    /**
     * @inheritDoc
     */
    public function latest(string $applicationUuid): ?array
    {
        $deployments = $this->forApplication($applicationUuid);

        // Reset array keys to ensure numeric indexing
        $deployments = array_values($deployments);

        return $deployments[0] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function trigger(string $applicationUuid, array $options = []): array
    {
        // Coolify API uses POST /deploy with uuid in body, not /applications/{uuid}/deploy
        $response = $this->client->post('deploy', array_merge([
            'uuid' => $applicationUuid,
        ], $options));

        // API returns {deployments: [{message, resource_uuid, deployment_uuid}]}
        // Extract the first deployment for backwards compatibility
        $deployment = $response['deployments'][0] ?? $response;

        return [
            'deployment_uuid' => $deployment['deployment_uuid'] ?? null,
            'message' => $deployment['message'] ?? null,
            'resource_uuid' => $deployment['resource_uuid'] ?? $applicationUuid,
        ];
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
        // Logs are embedded in the deployment response, not a separate endpoint
        $deployment = $this->get($uuid);
        $logsJson = $deployment['logs'] ?? '[]';

        // Logs are stored as JSON string
        $logs = is_string($logsJson) ? json_decode($logsJson, true) : $logsJson;

        return is_array($logs) ? $logs : [];
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
