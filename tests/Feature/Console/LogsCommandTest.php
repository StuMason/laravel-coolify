<?php

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

describe('LogsCommand', function () {
    it('displays application logs', function () {
        Http::fake([
            '*/applications/test-app-uuid/logs*' => Http::response([
                'logs' => "Starting application...\nApplication started successfully.",
            ]),
        ]);

        $this->artisan('coolify:logs')
            ->assertSuccessful();
    });

    it('handles missing logs gracefully', function () {
        Http::fake([
            '*/applications/test-app-uuid/logs*' => Http::response([
                'logs' => '',
            ]),
        ]);

        $this->artisan('coolify:logs')
            ->assertSuccessful();
    });

    it('uses specified uuid when provided', function () {
        Http::fake([
            '*/applications/custom-uuid/logs*' => Http::response([
                'logs' => 'Custom logs',
            ]),
        ]);

        $this->artisan('coolify:logs', ['--uuid' => 'custom-uuid'])
            ->assertSuccessful();
    });
});
