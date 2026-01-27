<?php

declare(strict_types=1);

namespace Stumason\Coolify\Contracts;

/**
 * Repository interface for Laravel Kick integration.
 *
 * Provides access to kick endpoints on remote Laravel applications
 * managed by Coolify.
 */
interface KickRepository
{
    /**
     * Check if kick is available for an application.
     * Returns config if available, null if not.
     *
     * @return array{base_url: string, token: string, kick_path: string}|null
     */
    public function getConfig(string $appUuid): ?array;

    /**
     * Check if kick endpoints are reachable.
     */
    public function isReachable(string $appUuid): bool;

    /**
     * Get health status from kick.
     *
     * @return array<string, mixed>|null
     */
    public function health(string $appUuid): ?array;

    /**
     * Get system stats from kick.
     *
     * @return array<string, mixed>|null
     */
    public function stats(string $appUuid): ?array;

    /**
     * List available log files.
     *
     * @return array<string, mixed>|null
     */
    public function logFiles(string $appUuid): ?array;

    /**
     * Read log entries.
     *
     * @return array<string, mixed>|null
     */
    public function logRead(string $appUuid, string $file, ?string $level = null, ?string $search = null, int $lines = 100): ?array;

    /**
     * Get queue status.
     *
     * @return array<string, mixed>|null
     */
    public function queueStatus(string $appUuid): ?array;

    /**
     * Get failed jobs.
     *
     * @return array<string, mixed>|null
     */
    public function queueFailed(string $appUuid, int $limit = 20): ?array;

    /**
     * Write test log entries.
     *
     * @return array<string, mixed>|null
     */
    public function logsTest(string $appUuid): ?array;

    /**
     * List available artisan commands.
     *
     * @return array<string, mixed>|null
     */
    public function artisanList(string $appUuid): ?array;

    /**
     * Execute an artisan command.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>|null
     */
    public function artisanRun(string $appUuid, string $command, array $arguments = []): ?array;

    /**
     * Clear cached kick configuration for an application.
     */
    public function clearCache(string $appUuid): void;
}
