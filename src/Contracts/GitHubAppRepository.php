<?php

namespace Stumason\Coolify\Contracts;

interface GitHubAppRepository
{
    /**
     * Get all GitHub Apps.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array;

    /**
     * Get a GitHub App by UUID.
     *
     * @return array<string, mixed>
     */
    public function get(string $uuid): array;

    /**
     * Get repositories for a GitHub App.
     *
     * @return array<int, array<string, mixed>>
     */
    public function repositories(string $uuid): array;

    /**
     * Get branches for a repository.
     *
     * @return array<int, array<string, mixed>>
     */
    public function branches(string $uuid, string $owner, string $repo): array;
}
