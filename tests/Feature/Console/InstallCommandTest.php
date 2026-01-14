<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    // Set as unconfigured so coolify:status is not called
    config(['coolify.token' => null]);

    // Clean up generated files
    $files = [
        base_path('Dockerfile'),
        base_path('docker/supervisord.conf'),
        base_path('docker/nginx.conf'),
        base_path('docker/php.ini'),
    ];
    foreach ($files as $file) {
        if (File::exists($file)) {
            File::delete($file);
        }
    }
    if (File::isDirectory(base_path('docker'))) {
        File::deleteDirectory(base_path('docker'));
    }
});

afterEach(function () {
    // Clean up generated files
    $files = [
        base_path('Dockerfile'),
        base_path('docker/supervisord.conf'),
        base_path('docker/nginx.conf'),
        base_path('docker/php.ini'),
    ];
    foreach ($files as $file) {
        if (File::exists($file)) {
            File::delete($file);
        }
    }
    if (File::isDirectory(base_path('docker'))) {
        File::deleteDirectory(base_path('docker'));
    }
});

describe('InstallCommand', function () {
    it('publishes the configuration file', function () {
        $configPath = config_path('coolify.php');

        // Clean up if exists
        if (File::exists($configPath)) {
            File::delete($configPath);
        }

        $this->artisan('coolify:install', ['--no-docker' => true])
            ->assertSuccessful();
    });

    it('displays installation success message', function () {
        $this->artisan('coolify:install', ['--no-docker' => true])
            ->expectsOutputToContain('Laravel Coolify installed successfully')
            ->assertSuccessful();
    });

    it('shows helpful next steps', function () {
        $this->artisan('coolify:install', ['--no-docker' => true])
            ->expectsOutputToContain('COOLIFY_TOKEN')
            ->assertSuccessful();
    });

    it('generates Dockerfile by default', function () {
        $this->artisan('coolify:install', ['--force' => true])
            ->assertSuccessful();

        expect(File::exists(base_path('Dockerfile')))->toBeTrue();
        expect(File::exists(base_path('docker/supervisord.conf')))->toBeTrue();
        expect(File::exists(base_path('docker/nginx.conf')))->toBeTrue();
        expect(File::exists(base_path('docker/php.ini')))->toBeTrue();

        expect(File::get(base_path('Dockerfile')))->toContain('FROM php:8.4-fpm-bookworm');
    });

    it('skips Docker generation with --no-docker flag', function () {
        $this->artisan('coolify:install', ['--no-docker' => true])
            ->assertSuccessful();

        expect(File::exists(base_path('Dockerfile')))->toBeFalse();
    });
});
