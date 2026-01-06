<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    // Set as unconfigured so coolify:status is not called
    config(['coolify.token' => null]);

    // Clean up nixpacks.toml
    $nixpacksPath = base_path('nixpacks.toml');
    if (File::exists($nixpacksPath)) {
        File::delete($nixpacksPath);
    }
});

afterEach(function () {
    // Clean up nixpacks.toml
    $nixpacksPath = base_path('nixpacks.toml');
    if (File::exists($nixpacksPath)) {
        File::delete($nixpacksPath);
    }
});

describe('InstallCommand', function () {
    it('publishes the configuration file', function () {
        $configPath = config_path('coolify.php');

        // Clean up if exists
        if (File::exists($configPath)) {
            File::delete($configPath);
        }

        $this->artisan('coolify:install', ['--no-nixpacks' => true])
            ->assertSuccessful();
    });

    it('displays installation success message', function () {
        $this->artisan('coolify:install', ['--no-nixpacks' => true])
            ->expectsOutputToContain('Laravel Coolify installed successfully')
            ->assertSuccessful();
    });

    it('shows helpful next steps', function () {
        $this->artisan('coolify:install', ['--no-nixpacks' => true])
            ->expectsOutputToContain('COOLIFY_TOKEN')
            ->assertSuccessful();
    });

    it('generates nixpacks.toml', function () {
        $nixpacksPath = base_path('nixpacks.toml');

        $this->artisan('coolify:install', ['--force' => true])
            ->assertSuccessful();

        expect(File::exists($nixpacksPath))->toBeTrue();
        expect(File::get($nixpacksPath))->toContain('[phases.build]');
    });

    it('skips nixpacks generation with --no-nixpacks flag', function () {
        $nixpacksPath = base_path('nixpacks.toml');

        $this->artisan('coolify:install', ['--no-nixpacks' => true])
            ->assertSuccessful();

        expect(File::exists($nixpacksPath))->toBeFalse();
    });
});
