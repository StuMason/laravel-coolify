<?php

use Illuminate\Support\Facades\Http;
use Stumason\Coolify\Contracts\TeamRepository;

describe('TeamRepository', function () {
    beforeEach(function () {
        $this->repository = app(TeamRepository::class);
    });

    it('lists all teams', function () {
        Http::fake([
            '*/teams' => Http::response([
                ['id' => 1, 'name' => 'Team One'],
                ['id' => 2, 'name' => 'Team Two'],
            ]),
        ]);

        $teams = $this->repository->all();

        expect($teams)->toBeArray()
            ->and($teams)->toHaveCount(2)
            ->and($teams[0]['name'])->toBe('Team One');
    });

    it('gets current team', function () {
        Http::fake([
            '*/teams/current' => Http::response([
                'id' => 1,
                'name' => 'Current Team',
                'description' => 'The active team',
            ]),
        ]);

        $team = $this->repository->current();

        expect($team)->toBeArray()
            ->and($team['id'])->toBe(1)
            ->and($team['name'])->toBe('Current Team');
    });

    it('gets team members', function () {
        Http::fake([
            '*/teams/current/members' => Http::response([
                ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
                ['id' => 2, 'name' => 'Jane Doe', 'email' => 'jane@example.com'],
            ]),
        ]);

        $members = $this->repository->members();

        expect($members)->toBeArray()
            ->and($members)->toHaveCount(2)
            ->and($members[0]['email'])->toBe('john@example.com');
    });

    it('gets a team by id', function () {
        Http::fake([
            '*/teams/1' => Http::response([
                'id' => 1,
                'name' => 'Specific Team',
            ]),
        ]);

        $team = $this->repository->get(1);

        expect($team)->toBeArray()
            ->and($team['id'])->toBe(1)
            ->and($team['name'])->toBe('Specific Team');
    });
});
