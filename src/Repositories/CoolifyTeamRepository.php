<?php

namespace Stumason\Coolify\Repositories;

use Stumason\Coolify\Contracts\TeamRepository;
use Stumason\Coolify\CoolifyClient;

class CoolifyTeamRepository implements TeamRepository
{
    public function __construct(
        protected CoolifyClient $client
    ) {}

    /**
     * Get all teams.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->client->get('teams');
    }

    /**
     * Get the current team.
     *
     * @return array<string, mixed>
     */
    public function current(): array
    {
        return $this->client->get('teams/current');
    }

    /**
     * Get team members.
     *
     * @return array<int, array<string, mixed>>
     */
    public function members(): array
    {
        return $this->client->get('teams/current/members');
    }

    /**
     * Get a team by ID.
     *
     * @return array<string, mixed>
     */
    public function get(int $id): array
    {
        return $this->client->get("teams/{$id}");
    }
}
