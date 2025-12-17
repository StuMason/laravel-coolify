<?php

namespace Stumason\Coolify\Contracts;

interface TeamRepository
{
    /**
     * Get all teams.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array;

    /**
     * Get the current team.
     *
     * @return array<string, mixed>
     */
    public function current(): array;

    /**
     * Get team members.
     *
     * @return array<int, array<string, mixed>>
     */
    public function members(): array;

    /**
     * Get a team by ID.
     *
     * @return array<string, mixed>
     */
    public function get(int $id): array;
}
