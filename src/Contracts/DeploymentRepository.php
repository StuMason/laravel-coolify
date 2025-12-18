<?php

namespace Stumason\Coolify\Contracts;

interface DeploymentRepository
{
    /**
     * Get all deployments.
     *
     * @return array<string, mixed>
     */
    public function all(): array;

    /**
     * Get a deployment by UUID.
     *
     * @return array<string, mixed>
     */
    public function get(string $uuid): array;

    /**
     * Get deployments for a specific application.
     *
     * @return array<string, mixed>
     */
    public function forApplication(string $applicationUuid): array;

    /**
     * Get the latest deployment for an application.
     *
     * @return array<string, mixed>|null
     */
    public function latest(string $applicationUuid): ?array;

    /**
     * Trigger a new deployment.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function trigger(string $applicationUuid, array $options = []): array;

    /**
     * Trigger a deployment by git tag.
     *
     * @return array<string, mixed>
     */
    public function deployTag(string $applicationUuid, string $tag): array;

    /**
     * Cancel a running deployment.
     *
     * @return array<string, mixed>
     */
    public function cancel(string $uuid): array;

    /**
     * Get deployment logs.
     *
     * @return array<string, mixed>
     */
    public function logs(string $uuid): array;

    /**
     * Rollback to a previous deployment.
     *
     * @return array<string, mixed>
     */
    public function rollback(string $applicationUuid, string $deploymentUuid): array;
}
