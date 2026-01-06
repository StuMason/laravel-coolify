<?php

declare(strict_types=1);

namespace Stumason\Coolify\Nixpacks\Detectors;

interface PackageDetector
{
    /**
     * Get the package name for display purposes.
     */
    public function name(): string;

    /**
     * Check if this package is installed.
     */
    public function isInstalled(): bool;

    /**
     * Get the processes this package requires.
     *
     * @return array<string, string> Process name => command
     */
    public function getProcesses(): array;

    /**
     * Get any Nix packages required for this detector.
     *
     * @return array<string>
     */
    public function getNixPackages(): array;

    /**
     * Get any build commands required.
     *
     * @return array<string>
     */
    public function getBuildCommands(): array;

    /**
     * Get any environment variables this package needs.
     *
     * @return array<string, string>
     */
    public function getEnvVars(): array;
}
