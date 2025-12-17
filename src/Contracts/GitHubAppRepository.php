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
     * @param  int|string  $id  The GitHub App's internal Coolify ID (numeric) or UUID
     * @return array<string, mixed>
     */
    public function repositories(int|string $id): array;

    /**
     * Get branches for a repository.
     *
     * @param  int|string  $id  The GitHub App's internal Coolify ID (numeric) or UUID
     * @return array<int, array<string, mixed>>
     */
    public function branches(int|string $id, string $owner, string $repo): array;
}
