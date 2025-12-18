<?php

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

describe('RestartCommand', function () {
    it('restarts the application', function () {
        Http::fake([
            '*/applications/test-app-uuid/restart' => Http::response([
                'message' => 'Application restarted',
            ]),
        ]);

        $this->artisan('coolify:restart', ['--force' => true])
            ->assertSuccessful();
    });

    it('displays success message', function () {
        Http::fake([
            '*/applications/test-app-uuid/restart' => Http::response([
                'message' => 'Application restarted',
            ]),
        ]);

        $this->artisan('coolify:restart', ['--force' => true])
            ->expectsOutputToContain('restart triggered successfully')
            ->assertSuccessful();
    });

    it('handles API errors gracefully', function () {
        Http::fake([
            '*/applications/test-app-uuid/restart' => Http::response(
                ['error' => 'Application not running'],
                400
            ),
        ]);

        $this->artisan('coolify:restart', ['--force' => true])
            ->assertFailed();
    });
});
