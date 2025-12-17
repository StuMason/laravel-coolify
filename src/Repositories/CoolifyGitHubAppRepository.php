<?php

namespace Stumason\Coolify\Repositories;

use Stumason\Coolify\Contracts\GitHubAppRepository;
use Stumason\Coolify\CoolifyClient;

class CoolifyGitHubAppRepository implements GitHubAppRepository
{
    public function __construct(
        protected CoolifyClient $client
    ) {}

    /**
     * @inheritDoc
     */
    public function all(): array
    {
        return $this->client->get('github-apps');
    }

    /**
     * @inheritDoc
     */
    public function get(string $uuid): array
    {
        return $this->client->get("github-apps/{$uuid}");
    }

    /**
     * @inheritDoc
     */
    public function repositories(string $uuid): array
    {
        return $this->client->get("github-apps/{$uuid}/repositories");
    }

    /**
     * @inheritDoc
     */
    public function branches(string $uuid, string $owner, string $repo): array
    {
        return $this->client->get("github-apps/{$uuid}/repositories/{$owner}/{$repo}/branches");
    }
}
