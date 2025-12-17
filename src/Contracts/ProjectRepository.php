<?php

namespace Stumason\Coolify\Contracts;

interface ProjectRepository
{
    /**
     * Get all projects.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array;

    /**
     * Get a project by UUID.
     *
     * @return array<string, mixed>
     */
    public function get(string $uuid): array;

    /**
     * Create a new project.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data): array;

    /**
     * Update a project.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(string $uuid, array $data): array;

    /**
     * Delete a project.
     */
    public function delete(string $uuid): bool;

    /**
     * Get project environments.
     *
     * @return array<int, array<string, mixed>>
     */
    public function environments(string $uuid): array;

    /**
     * Get a specific environment.
     *
     * @return array<string, mixed>
     */
    public function environment(string $projectUuid, string $environmentName): array;
}
