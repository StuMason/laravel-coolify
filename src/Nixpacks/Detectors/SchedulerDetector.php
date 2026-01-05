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
        // Check if Console/Kernel.php has scheduled tasks defined
        // or if routes/console.php has Schedule usage
        $kernelPath = app_path('Console/Kernel.php');
        $consolePath = base_path('routes/console.php');

        // Check Kernel.php for schedule method with actual tasks
        if (File::exists($kernelPath)) {
            $content = File::get($kernelPath);
            // Look for actual schedule commands, not just empty method
            if (preg_match('/\$schedule->(command|call|job|exec)\s*\(/', $content)) {
                return true;
            }
        }

        // Check routes/console.php for Schedule facade usage (Laravel 11+ style)
        if (File::exists($consolePath)) {
            $content = File::get($consolePath);
            if (str_contains($content, 'Schedule::')) {
                return true;
            }
        }

        return false;
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
