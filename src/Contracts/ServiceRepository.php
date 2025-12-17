<?php

namespace Stumason\Coolify\Contracts;

interface ServiceRepository
{
    /**
     * Get all services.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array;

    /**
     * Get a service by UUID.
     *
     * @return array<string, mixed>
     */
    public function get(string $uuid): array;

    /**
     * Create a new one-click service.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data): array;

    /**
     * Update a service.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(string $uuid, array $data): array;

    /**
     * Delete a service.
     */
    public function delete(string $uuid): bool;

    /**
     * Start a service.
     *
     * @return array<string, mixed>
     */
    public function start(string $uuid): array;

    /**
     * Stop a service.
     *
     * @return array<string, mixed>
     */
    public function stop(string $uuid): array;

    /**
     * Restart a service.
     *
     * @return array<string, mixed>
     */
    public function restart(string $uuid): array;
}
