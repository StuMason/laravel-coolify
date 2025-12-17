<?php

use Illuminate\Support\Facades\Http;
use Stumason\Coolify\Coolify;

describe('TeamController', function () {
    beforeEach(function () {
        Coolify::auth(fn () => true);
    });

    it('returns all teams', function () {
        Http::fake([
            '*/teams' => Http::response([
                ['id' => 1, 'name' => 'Team One'],
                ['id' => 2, 'name' => 'Team Two'],
            ]),
        ]);

        $response = $this->getJson('/coolify/api/teams');

        $response->assertOk()
            ->assertJsonCount(2);
    });

    it('returns the current team', function () {
        Http::fake([
            '*/teams/current' => Http::response([
                'id' => 1,
                'name' => 'Current Team',
                'description' => 'The active team',
            ]),
        ]);

        $response = $this->getJson('/coolify/api/teams/current');

        $response->assertOk()
            ->assertJsonPath('id', 1)
            ->assertJsonPath('name', 'Current Team');
    });

    it('returns team members', function () {
        Http::fake([
            '*/teams/current/members' => Http::response([
                ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
                ['id' => 2, 'name' => 'Jane Doe', 'email' => 'jane@example.com'],
            ]),
        ]);

        $response = $this->getJson('/coolify/api/teams/current/members');

        $response->assertOk()
            ->assertJsonCount(2);
    });

    it('requires authentication', function () {
        Coolify::auth(fn () => false);

        Http::fake([
            '*/teams' => Http::response([]),
        ]);

        $response = $this->getJson('/coolify/api/teams');

        $response->assertForbidden();
    });
});
