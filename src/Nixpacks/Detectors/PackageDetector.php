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
     * Get the supervisor worker configuration for this package.
     * Returns null if no worker is needed.
     *
     * The config should be a valid supervisor [program:x] section.
     */
    public function getSupervisorConfig(): ?string;

    /**
     * Get any nginx location blocks required by this package.
     * For example, Reverb needs a WebSocket proxy block.
     *
     * @return array<string> Array of nginx location block strings
     */
    public function getNginxLocationBlocks(): array;

    /**
     * Get any PHP extensions required by this package.
     * These will be added to the php.withExtensions nix expression.
     *
     * @return array<string> Extension names (e.g., 'redis', 'pcntl')
     */
    public function getPhpExtensions(): array;
}
