<?php

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

describe('coolify:status command', function () {
    it('shows error when not configured', function () {
        config(['coolify.token' => null]);

        $this->artisan('coolify:status')
            ->assertFailed()
            ->expectsOutputToContain('not configured');
    });

    it('shows error when no application UUID', function () {
        config(['coolify.application_uuid' => null]);

        Http::fake(['*' => Http::response(['version' => '4.0'], 200)]);

        $this->artisan('coolify:status')
            ->assertFailed()
            ->expectsOutputToContain('No application UUID');
    });

    it('shows application status', function () {
        Http::fake([
            '*/version' => Http::response(['version' => '4.0'], 200),
            '*/applications/test-app-uuid' => Http::response([
                'uuid' => 'test-app-uuid',
                'name' => 'My Laravel App',
                'status' => 'running',
                'fqdn' => 'https://myapp.com',
                'git_repository' => 'https://github.com/user/repo',
                'git_branch' => 'main',
            ], 200),
        ]);

        $this->artisan('coolify:status')
            ->assertSuccessful()
            ->expectsOutputToContain('My Laravel App')
            ->expectsOutputToContain('running');
    });

    it('shows all resources with --all flag', function () {
        Http::fake([
            '*/version' => Http::response(['version' => '4.0'], 200),
            '*/applications' => Http::response([
                ['uuid' => 'app-1', 'name' => 'App 1', 'status' => 'running', 'fqdn' => 'app1.com'],
            ], 200),
            '*/databases' => Http::response([
                ['uuid' => 'db-1', 'name' => 'Database', 'type' => 'postgresql', 'status' => 'running'],
            ], 200),
        ]);

        $this->artisan('coolify:status', ['--all' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Applications')
            ->expectsOutputToContain('Databases');
    });
});
