<?php

use Illuminate\Support\Facades\Http;
use Stumason\Coolify\Contracts\ProjectRepository;

describe('ProjectRepository', function () {
    beforeEach(function () {
        $this->repository = app(ProjectRepository::class);
    });

    it('lists all projects', function () {
        Http::fake([
            '*/projects' => Http::response([
                ['uuid' => 'project-1', 'name' => 'Project One'],
                ['uuid' => 'project-2', 'name' => 'Project Two'],
            ]),
        ]);

        $projects = $this->repository->all();

        expect($projects)->toBeArray()
            ->and($projects)->toHaveCount(2)
            ->and($projects[0]['name'])->toBe('Project One');
    });

    it('gets a project by uuid', function () {
        Http::fake([
            '*/projects/project-uuid-123' => Http::response([
                'uuid' => 'project-uuid-123',
                'name' => 'My Project',
                'description' => 'A test project',
            ]),
        ]);

        $project = $this->repository->get('project-uuid-123');

        expect($project)->toBeArray()
            ->and($project['uuid'])->toBe('project-uuid-123')
            ->and($project['name'])->toBe('My Project');
    });

    it('gets project environments', function () {
        Http::fake([
            '*/projects/project-uuid-123/environments' => Http::response([
                ['id' => 1, 'name' => 'production'],
                ['id' => 2, 'name' => 'staging'],
            ]),
        ]);

        $environments = $this->repository->environments('project-uuid-123');

        expect($environments)->toBeArray()
            ->and($environments)->toHaveCount(2)
            ->and($environments[0]['name'])->toBe('production');
    });
});
