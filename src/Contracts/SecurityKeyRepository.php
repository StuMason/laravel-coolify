<?php

namespace Stumason\Coolify\Contracts;

interface SecurityKeyRepository
{
    /**
     * Get all SSH keys.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array;

    /**
     * Get a specific SSH key by UUID.
     *
     * @return array<string, mixed>
     */
    public function get(string $uuid): array;

    /**
     * Create a new SSH key.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data): array;

    /**
     * Update an SSH key.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(string $uuid, array $data): array;

    /**
     * Delete an SSH key.
     */
    public function delete(string $uuid): bool;
}
