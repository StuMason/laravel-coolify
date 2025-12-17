<?php

use Illuminate\Support\Facades\File;

describe('InstallCommand', function () {
    it('publishes the configuration file', function () {
        $configPath = config_path('coolify.php');

        // Clean up if exists
        if (File::exists($configPath)) {
            File::delete($configPath);
        }

        $this->artisan('coolify:install')
            ->assertSuccessful();
    });

    it('displays installation success message', function () {
        $this->artisan('coolify:install')
            ->expectsOutputToContain('Coolify scaffolding installed successfully')
            ->assertSuccessful();
    });

    it('shows helpful next steps', function () {
        $this->artisan('coolify:install')
            ->expectsOutputToContain('COOLIFY_TOKEN')
            ->assertSuccessful();
    });
});
