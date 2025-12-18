<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    // Set as unconfigured so coolify:status is not called
    config(['coolify.token' => null]);
});

describe('InstallCommand', function () {
    it('publishes the configuration file', function () {
        $configPath = config_path('coolify.php');

        // Clean up if exists
        if (File::exists($configPath)) {
            File::delete($configPath);
        }

        $this->artisan('coolify-dashboard:install')
            ->assertSuccessful();
    });

    it('displays installation success message', function () {
        $this->artisan('coolify-dashboard:install')
            ->expectsOutputToContain('Coolify Dashboard installed successfully')
            ->assertSuccessful();
    });

    it('shows helpful next steps', function () {
        $this->artisan('coolify-dashboard:install')
            ->expectsOutputToContain('COOLIFY_TOKEN')
            ->assertSuccessful();
    });
});
