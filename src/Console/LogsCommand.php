<?php

namespace Stumason\Coolify\Console;

use Illuminate\Console\Command;
use Stumason\Coolify\Contracts\ApplicationRepository;
use Stumason\Coolify\Contracts\DeploymentRepository;
use Stumason\Coolify\Coolify;
use Stumason\Coolify\CoolifyClient;
use Stumason\Coolify\Exceptions\CoolifyApiException;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'coolify:logs')]
class LogsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coolify:logs
                            {--uuid= : Application UUID (defaults to config)}
                            {--deployment= : Show logs for a specific deployment}
                            {--lines=100 : Number of lines to retrieve}
                            {--follow : Continuously poll for new logs}
                            {--debug : Show debug/build logs (hidden by default)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'View application or deployment logs from Coolify';

    /**
     * Execute the console command.
     */
    public function handle(
        CoolifyClient $client,
        ApplicationRepository $applications,
        DeploymentRepository $deployments
    ): int {
        if (! $client->isConfigured()) {
            $this->components->error('Coolify is not configured. Please set COOLIFY_URL and COOLIFY_TOKEN in your .env file.');

            return self::FAILURE;
        }

        $deploymentUuid = $this->option('deployment');

        if ($deploymentUuid) {
            return $this->showDeploymentLogs($deployments, $deploymentUuid);
        }

        return $this->showApplicationLogs($applications);
    }

    /**
     * Show application logs.
     */
    protected function showApplicationLogs(ApplicationRepository $applications): int
    {
        $uuid = $this->option('uuid') ?? Coolify::applicationUuid();

        if (! $uuid) {
            $this->components->error('No application configured. Set COOLIFY_PROJECT_UUID or COOLIFY_APPLICATION_UUID in your .env file, or use --uuid option.');

            return self::FAILURE;
        }

        $lines = (int) $this->option('lines');
        $follow = $this->option('follow');

        try {
            if ($follow) {
                return $this->followLogs($applications, $uuid, $lines);
            }

            $logs = $applications->logs($uuid, $lines);
            $this->outputLogs($logs);

        } catch (CoolifyApiException $e) {
            $this->components->error("Failed to fetch logs: {$e->getMessage()}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Show deployment logs.
     */
    protected function showDeploymentLogs(DeploymentRepository $deployments, string $uuid): int
    {
        try {
            $logs = $deployments->logs($uuid);
            $this->outputLogs($logs);

        } catch (CoolifyApiException $e) {
            $this->components->error("Failed to fetch deployment logs: {$e->getMessage()}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Output logs to console.
     *
     * @param  array<string, mixed>  $logs
     */
    protected function outputLogs(array $logs): void
    {
        $showDebug = $this->option('debug');

        // Handle different log formats from Coolify API
        $logContent = $logs['logs'] ?? $logs['output'] ?? $logs;

        if (is_array($logContent)) {
            foreach ($logContent as $line) {
                if (is_string($line)) {
                    $this->line($this->formatLogLine($line));
                } elseif (is_array($line)) {
                    // Coolify deployment logs format: {output, hidden, type, timestamp, command}
                    $isHidden = $line['hidden'] ?? false;

                    // Skip hidden logs unless --debug flag is set
                    if ($isHidden && ! $showDebug) {
                        continue;
                    }

                    $output = $line['output'] ?? $line['message'] ?? null;
                    $type = $line['type'] ?? 'stdout';
                    $timestamp = $line['timestamp'] ?? null;

                    if ($output) {
                        // Format timestamp if present
                        $prefix = '';
                        if ($timestamp && $showDebug) {
                            $time = substr($timestamp, 11, 8); // Extract HH:MM:SS
                            $prefix = "<fg=gray>[{$time}]</> ";
                        }

                        // Color based on type
                        if ($type === 'stderr') {
                            $this->line($prefix.$this->formatLogLine($output, 'stderr'));
                        } else {
                            $this->line($prefix.$this->formatLogLine($output));
                        }
                    }
                }
            }
        } elseif (is_string($logContent)) {
            foreach (explode("\n", $logContent) as $line) {
                $this->line($this->formatLogLine($line));
            }
        }
    }

    /**
     * Format a log line with colors.
     */
    protected function formatLogLine(string $line, string $type = 'stdout'): string
    {
        // stderr is always yellow/red
        if ($type === 'stderr') {
            if (str_contains(strtolower($line), 'error')) {
                return "<fg=red>{$line}</>";
            }

            return "<fg=yellow>{$line}</>";
        }

        // Add color coding based on log level
        if (str_contains(strtolower($line), 'error')) {
            return "<fg=red>{$line}</>";
        }

        if (str_contains(strtolower($line), 'warning') || str_contains(strtolower($line), 'warn')) {
            return "<fg=yellow>{$line}</>";
        }

        if (str_contains(strtolower($line), 'info')) {
            return "<fg=blue>{$line}</>";
        }

        return $line;
    }

    /**
     * Follow logs in real-time.
     */
    protected function followLogs(ApplicationRepository $applications, string $uuid, int $lines): int
    {
        $this->components->info("Following logs for {$uuid}... (Ctrl+C to stop)");
        $this->newLine();

        $lastLogs = '';

        while (true) {
            try {
                $logs = $applications->logs($uuid, $lines);
                $logContent = $logs['logs'] ?? $logs['output'] ?? '';

                if (is_array($logContent)) {
                    $logContent = implode("\n", array_map(fn ($l) => is_string($l) ? $l : ($l['message'] ?? ''), $logContent));
                }

                // Only output new content
                if ($logContent !== $lastLogs) {
                    // Find new lines by comparing
                    $newContent = $this->getNewContent($lastLogs, $logContent);
                    if ($newContent) {
                        foreach (explode("\n", $newContent) as $line) {
                            if (trim($line)) {
                                $this->line($this->formatLogLine($line));
                            }
                        }
                    }
                    $lastLogs = $logContent;
                }

            } catch (CoolifyApiException $e) {
                $this->components->error("Lost connection: {$e->getMessage()}");

                return self::FAILURE;
            }

            sleep(2);
        }
    }

    /**
     * Get new content from log comparison.
     */
    protected function getNewContent(string $old, string $new): string
    {
        if (empty($old)) {
            return $new;
        }

        // Find the position where old content ends in new content
        $pos = strpos($new, substr($old, -100));

        if ($pos !== false) {
            return substr($new, $pos + strlen(substr($old, -100)));
        }

        return $new;
    }
}
