<?php

declare(strict_types=1);

namespace Stumason\Coolify\Nixpacks\Detectors;

class OctaneDetector implements PackageDetector
{
    public function name(): string
    {
        return 'Laravel Octane';
    }

    public function isInstalled(): bool
    {
        return class_exists(\Laravel\Octane\Octane::class);
    }

    public function getProcesses(): array
    {
        // Octane replaces php-fpm as the web process
        // Return empty here - the generator handles swapping the web process
        return [];
    }

    public function getNixPackages(): array
    {
        // Determine which server is configured
        $server = config('octane.server', 'swoole');

        if ($server === 'swoole') {
            return ['php83Extensions.swoole'];
        }

        if ($server === 'roadrunner') {
            return ['roadrunner'];
        }

        // FrankenPHP doesn't need extra nix packages
        return [];
    }

    public function getBuildCommands(): array
    {
        return [];
    }

    public function getEnvVars(): array
    {
        return [];
    }

    /**
     * Get the Octane start command based on configured server.
     */
    public function getWebCommand(): string
    {
        $server = config('octane.server', 'swoole');

        return match ($server) {
            'swoole' => 'php artisan octane:start --server=swoole --host=0.0.0.0 --port=8000',
            'roadrunner' => 'php artisan octane:start --server=roadrunner --host=0.0.0.0 --port=8000',
            'frankenphp' => 'php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=8000',
            default => 'php artisan octane:start --host=0.0.0.0 --port=8000',
        };
    }
}
