<?php

declare(strict_types=1);

namespace Stumason\Coolify\Nixpacks\Detectors;

class ReverbDetector implements PackageDetector
{
    public function name(): string
    {
        return 'Laravel Reverb';
    }

    public function isInstalled(): bool
    {
        return class_exists(\Laravel\Reverb\Reverb::class);
    }

    public function getProcesses(): array
    {
        return [
            'reverb' => 'php artisan reverb:start --host=0.0.0.0 --port=8080',
        ];
    }

    public function getNixPackages(): array
    {
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
