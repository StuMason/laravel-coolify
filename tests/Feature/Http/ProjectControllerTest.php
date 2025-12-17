<?php

use Illuminate\Support\Facades\Http;
use Stumason\Coolify\Coolify;

describe('ProjectController', function () {
    beforeEach(function () {
        Coolify::auth(fn () => true);
    });

    it('returns all projects', function () {
        Http::fake([
            '*/projects' => Http::response([
                ['uuid' => 'project-1', 'name' => 'Project One'],
                ['uuid' => 'project-2', 'name' => 'Project Two'],
            ]),
        ]);

        $response = $this->getJson('/coolify/api/projects');

        $response->assertOk()
            ->assertJsonCount(2);
    });

    it('returns a single project', function () {
        Http::fake([
            '*/projects/project-uuid-123' => Http::response([
                'uuid' => 'project-uuid-123',
                'name' => 'My Project',
                'description' => 'A test project',
            ]),
        ]);

        $response = $this->getJson('/coolify/api/projects/project-uuid-123');

        $response->assertOk()
            ->assertJsonPath('uuid', 'project-uuid-123')
            ->assertJsonPath('name', 'My Project');
    });

    it('returns project environments', function () {
        Http::fake([
            '*/projects/project-uuid-123/environments' => Http::response([
                ['id' => 1, 'name' => 'production'],
                ['id' => 2, 'name' => 'staging'],
            ]),
        ]);

        $response = $this->getJson('/coolify/api/projects/project-uuid-123/environments');

        $response->assertOk()
            ->assertJsonCount(2);
    });

    it('requires authentication', function () {
        Coolify::auth(fn () => false);

        Http::fake([
            '*/projects' => Http::response([]),
        ]);

        $response = $this->getJson('/coolify/api/projects');

        $response->assertForbidden();
    });
});
