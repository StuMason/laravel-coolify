<?php

declare(strict_types=1);

namespace Stumason\Coolify\Nixpacks\Detectors;

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
        // This is the preferred location for scheduled tasks in modern Laravel
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
            // If tokenization fails (syntax error), return original content
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

    public function getProcesses(): array
    {
        return [
            'scheduler' => 'php artisan schedule:work',
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
