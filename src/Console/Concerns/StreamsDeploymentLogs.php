<?php

namespace Stumason\Coolify\Console\Concerns;

use Stumason\Coolify\Contracts\DeploymentRepository;
use Stumason\Coolify\Exceptions\CoolifyApiException;

trait StreamsDeploymentLogs
{
    /**
     * Stream deployment logs in real-time until deployment completes.
     *
     * @param  bool  $showDebug  Show hidden/debug logs (where the interesting stuff is)
     */
    protected function streamDeploymentLogs(
        DeploymentRepository $deployments,
        string $deploymentUuid,
        bool $showDebug = true
    ): int {
        $this->newLine();
        $this->components->info('Streaming deployment logs...');
        $this->newLine();

        $maxAttempts = 180; // 15 minutes with 5 second intervals
        $attempts = 0;
        $lastLogCount = 0;
        $status = 'in_progress';

        while ($attempts < $maxAttempts) {
            try {
                // Fetch deployment status and logs
                $deployment = $deployments->get($deploymentUuid);
                $status = strtolower($deployment['status'] ?? 'unknown');

                // Fetch and display new log entries
                $logs = $deployments->logs($deploymentUuid);
                $logEntries = $logs['logs'] ?? $logs['output'] ?? $logs ?? [];

                if (is_array($logEntries)) {
                    // Output only new log entries
                    $newEntries = array_slice($logEntries, $lastLogCount);
                    foreach ($newEntries as $entry) {
                        $this->outputLogEntry($entry, $showDebug);
                    }
                    $lastLogCount = count($logEntries);
                }

                // Check if deployment is complete
                if (in_array($status, ['finished', 'success'])) {
                    $this->newLine();
                    $this->components->info('Deployment completed successfully!');

                    return self::SUCCESS;
                }

                if (in_array($status, ['failed', 'error'])) {
                    $this->newLine();
                    $this->components->error('Deployment failed.');

                    return self::FAILURE;
                }

                if ($status === 'cancelled') {
                    $this->newLine();
                    $this->components->warn('Deployment was cancelled.');

                    return self::FAILURE;
                }

            } catch (CoolifyApiException $e) {
                $this->components->error("Failed to fetch deployment status: {$e->getMessage()}");

                return self::FAILURE;
            }

            sleep(5);
            $attempts++;
        }

        $this->newLine();
        $this->components->warn('Timed out waiting for deployment. It may still be in progress.');
        $this->line("  Run <comment>php artisan coolify:logs --deployment={$deploymentUuid} --debug</comment> to view logs.");

        return self::SUCCESS;
    }

    /**
     * Output a single log entry.
     */
    protected function outputLogEntry(mixed $entry, bool $showDebug): void
    {
        if (is_string($entry)) {
            $this->line($this->formatDeploymentLogLine($entry));

            return;
        }

        if (! is_array($entry)) {
            return;
        }

        // Coolify deployment logs format: {output, hidden, type, timestamp, command}
        $isHidden = $entry['hidden'] ?? false;

        // Skip hidden logs unless showDebug is enabled
        if ($isHidden && ! $showDebug) {
            return;
        }

        $output = $entry['output'] ?? $entry['message'] ?? null;
        $type = $entry['type'] ?? 'stdout';
        $timestamp = $entry['timestamp'] ?? null;

        if (! $output) {
            return;
        }

        // Format timestamp if present and debug mode
        $prefix = '';
        if ($timestamp && $showDebug) {
            $time = substr($timestamp, 11, 8); // Extract HH:MM:SS
            $prefix = "<fg=gray>[{$time}]</> ";
        }

        // Color based on type
        if ($type === 'stderr') {
            $this->line($prefix.$this->formatDeploymentLogLine($output, 'stderr'));
        } else {
            $this->line($prefix.$this->formatDeploymentLogLine($output));
        }
    }

    /**
     * Format a deployment log line with colors.
     */
    protected function formatDeploymentLogLine(string $line, string $type = 'stdout'): string
    {
        // stderr is always yellow/red
        if ($type === 'stderr') {
            if (str_contains(strtolower($line), 'error')) {
                return "<fg=red>{$line}</>";
            }

            return "<fg=yellow>{$line}</>";
        }

        // Highlight important build steps
        if (str_starts_with($line, '===') || str_starts_with($line, '---')) {
            return "<fg=cyan;options=bold>{$line}</>";
        }

        // Error highlighting
        if (str_contains(strtolower($line), 'error')) {
            return "<fg=red>{$line}</>";
        }

        // Warning highlighting
        if (str_contains(strtolower($line), 'warning') || str_contains(strtolower($line), 'warn')) {
            return "<fg=yellow>{$line}</>";
        }

        // Success indicators
        if (str_contains(strtolower($line), 'success') || str_contains(strtolower($line), 'completed')) {
            return "<fg=green>{$line}</>";
        }

        return $line;
    }
}
