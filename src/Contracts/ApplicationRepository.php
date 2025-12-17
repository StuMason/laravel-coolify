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
     * Create a new public git application.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createPublic(array $data): array;

    /**
     * Create a new private git application (GitHub App).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createPrivateGithubApp(array $data): array;

    /**
     * Create a new private git application (Deploy Key).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createPrivateDeployKey(array $data): array;

    /**
     * Create a new Dockerfile application.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createDockerfile(array $data): array;

    /**
     * Create a new Docker image application.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createDockerImage(array $data): array;

    /**
     * Create a new Docker Compose application.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createDockerCompose(array $data): array;

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
     * Create a new environment variable for an application.
     *
     * @param  array<string, mixed>  $env  Should contain 'key', 'value', and optionally 'is_buildtime'
     * @return array<string, mixed>
     */
    public function createEnv(string $uuid, array $env): array;

    /**
     * Update an existing environment variable for an application.
     *
     * @param  array<string, mixed>  $env  Should contain 'key' and 'value'
     * @return array<string, mixed>
     */
    public function updateEnv(string $uuid, array $env): array;
}
