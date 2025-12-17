<?php

namespace Stumason\Coolify\Contracts;

interface ServerRepository
{
    /**
     * Get all servers.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array;

    /**
     * Get a server by UUID.
     *
     * @return array<string, mixed>
     */
    public function get(string $uuid): array;

    /**
     * Create a new server.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data): array;

    /**
     * Update a server.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(string $uuid, array $data): array;

    /**
     * Delete a server.
     */
    public function delete(string $uuid): bool;

    /**
     * Get server resources (applications, databases, services).
     *
     * @return array<string, mixed>
     */
    public function resources(string $uuid): array;

    /**
     * Get server domains.
     *
     * @return array<int, array<string, mixed>>
     */
    public function domains(string $uuid): array;

    /**
     * Validate a server connection.
     *
     * @return array<string, mixed>
     */
    public function validate(string $uuid): array;
}
