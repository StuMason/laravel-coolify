<?php

declare(strict_types=1);

namespace Stumason\Coolify\Detectors;

use Illuminate\Support\Facades\File;

class SchedulerDetector implements PackageDetector
{
    public function name(): string
    {
        return 'Task Scheduler';
    }

    public function isInstalled(): bool
    {
        // Check routes/console.php first (Laravel 11+ style - more common now)
        $consolePath = base_path('routes/console.php');
        if (File::exists($consolePath)) {
            $content = $this->stripComments(File::get($consolePath));
            if (str_contains($content, 'Schedule::')) {
                return true;
            }
        }

        // Fall back to Console/Kernel.php for older Laravel versions
        $kernelPath = app_path('Console/Kernel.php');
        if (File::exists($kernelPath)) {
            $content = $this->stripComments(File::get($kernelPath));
            // Look for actual schedule commands, not just empty method
            if (preg_match('/\$schedule->(command|call|job|exec)\s*\(/', $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Strip PHP comments from content to avoid false positives.
     * Uses PHP's tokenizer for accurate parsing.
     */
    protected function stripComments(string $content): string
    {
        try {
            $tokens = @token_get_all($content);
        } catch (\Throwable) {
            return $content;
        }

        $output = '';

        foreach ($tokens as $token) {
            if (is_string($token)) {
                $output .= $token;
            } elseif ($token[0] !== T_COMMENT && $token[0] !== T_DOC_COMMENT) {
                $output .= $token[1];
            }
        }

        return $output;
    }

    public function getSupervisorConfig(): ?string
    {
        return <<<'CONF'
[program:worker-scheduler]
process_name=%(program_name)s
command=php /app/artisan schedule:work
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=1
startsecs=0
stdout_logfile=/var/log/worker-scheduler.log
stderr_logfile=/var/log/worker-scheduler.log
CONF;
    }

    public function getNginxLocationBlocks(): array
    {
        return [];
    }

    public function getPhpExtensions(): array
    {
        return [];
    }
}
