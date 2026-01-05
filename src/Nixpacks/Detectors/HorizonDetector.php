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
        // Horizon requires Redis - use phpXXExtensions based on PHP version
        $phpVersion = $this->getPhpMajorMinor();

        return ["php{$phpVersion}Extensions.redis"];
    }

    protected function getPhpMajorMinor(): string
    {
        return PHP_MAJOR_VERSION.PHP_MINOR_VERSION;
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
