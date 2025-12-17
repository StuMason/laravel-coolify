<?php

use Illuminate\Support\Facades\Http;

describe('RestartCommand', function () {
    it('restarts the application', function () {
        Http::fake([
            '*/applications/test-app-uuid' => Http::response([
                'uuid' => 'test-app-uuid',
                'name' => 'Test App',
            ]),
            '*/applications/test-app-uuid/restart' => Http::response([
                'message' => 'Application restarted',
            ]),
        ]);

        $this->artisan('coolify:restart')
            ->assertSuccessful();
    });

    it('displays success message', function () {
        Http::fake([
            '*/applications/test-app-uuid' => Http::response([
                'uuid' => 'test-app-uuid',
                'name' => 'Test App',
            ]),
            '*/applications/test-app-uuid/restart' => Http::response([
                'message' => 'Application restarted',
            ]),
        ]);

        $this->artisan('coolify:restart')
            ->expectsOutputToContain('restarted')
            ->assertSuccessful();
    });

    it('handles API errors gracefully', function () {
        Http::fake([
            '*/applications/test-app-uuid' => Http::response([
                'uuid' => 'test-app-uuid',
                'name' => 'Test App',
            ]),
            '*/applications/test-app-uuid/restart' => Http::response(
                ['error' => 'Application not running'],
                400
            ),
        ]);

        $this->artisan('coolify:restart')
            ->assertFailed();
    });
});
