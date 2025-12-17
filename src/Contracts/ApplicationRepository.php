<?php

namespace Stumason\Coolify\Contracts;

interface ApplicationRepository
{
    /**
     * Get all applications.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array;

    /**
     * Get an application by UUID.
     *
     * @return array<string, mixed>
     */
    public function get(string $uuid): array;

    /**
     * Create a new application.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data): array;

    /**
     * Update an application.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(string $uuid, array $data): array;

    /**
     * Delete an application.
     */
    public function delete(string $uuid): bool;

    /**
     * Deploy an application.
     *
     * @return array<string, mixed>
     */
    public function deploy(string $uuid): array;

    /**
     * Start an application.
     *
     * @return array<string, mixed>
     */
    public function start(string $uuid): array;

    /**
     * Stop an application.
     *
     * @return array<string, mixed>
     */
    public function stop(string $uuid): array;

    /**
     * Restart an application.
     *
     * @return array<string, mixed>
     */
    public function restart(string $uuid): array;

    /**
     * Get application logs.
     *
     * @return array<string, mixed>
     */
    public function logs(string $uuid, int $lines = 100): array;

    /**
     * Get environment variables for an application.
     *
     * @return array<int, array<string, mixed>>
     */
    public function envs(string $uuid): array;

    /**
     * Update environment variables for an application.
     *
     * @param  array<string, mixed>  $envs
     * @return array<string, mixed>
     */
    public function updateEnvs(string $uuid, array $envs): array;
}
