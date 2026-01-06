<?php

declare(strict_types=1);

namespace Stumason\Coolify\Nixpacks\Detectors;

class HorizonDetector implements PackageDetector
{
    public function name(): string
    {
        return 'Laravel Horizon';
    }

    public function isInstalled(): bool
    {
        return class_exists(\Laravel\Horizon\Horizon::class);
    }

    public function getProcesses(): array
    {
        return [
            'horizon' => 'php artisan horizon',
        ];
    }

    public function getNixPackages(): array
    {
        // Nixpacks auto-detects PHP extensions from composer.json's ext-* requirements
        // Users should add "ext-redis": "*" to composer.json for Redis support
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
}
