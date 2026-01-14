<?php

declare(strict_types=1);

namespace Stumason\Coolify\Console;

use Illuminate\Console\Command;
use Stumason\Coolify\Ci\CiGenerator;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

#[AsCommand(name: 'coolify:setup-ci')]
class SetupCiCommand extends Command
{
    protected $signature = 'coolify:setup-ci
                            {--branch=main : Branch to deploy from}
                            {--force : Overwrite existing workflow}';

    protected $description = 'Generate GitHub Actions workflow for automatic deployments';

    public function handle(): int
    {
        $generator = new CiGenerator;

        // Get branch
        $branch = $this->option('branch');

        if (! $this->option('no-interaction')) {
            $branch = text(
                label: 'Which branch should trigger deployments?',
                default: $branch,
                required: true
            );
        }

        $generator->branch($branch);

        // Check if file already exists
        if ($generator->exists() && ! $this->option('force')) {
            if ($this->option('no-interaction')) {
                warning('GitHub Actions workflow already exists. Use --force to overwrite.');

                return self::FAILURE;
            }

            if (! confirm(label: 'GitHub Actions workflow already exists. Overwrite?', default: false)) {
                warning('Skipping workflow generation.');

                return self::SUCCESS;
            }
        }

        // Generate the workflow
        $filePath = $generator->write();
        $relativePath = str_replace(base_path().'/', '', $filePath);

        $this->components->task($relativePath, fn () => true);

        $this->newLine();
        info('GitHub Actions workflow generated!');
        $this->newLine();

        $this->line('  <fg=cyan>Add these secrets to your GitHub repository:</>');
        $this->line('  <fg=white>Settings → Secrets and variables → Actions</>');
        $this->newLine();

        $this->components->bulletList([
            '<comment>COOLIFY_URL</comment> - Your Coolify instance URL',
            '<comment>COOLIFY_TOKEN</comment> - Your Coolify API token',
            '<comment>COOLIFY_APPLICATION_UUID</comment> - The application UUID',
        ]);

        // Show current values if configured
        $url = config('coolify.url');
        $uuid = config('coolify.application_uuid');

        if ($url || $uuid) {
            $this->newLine();
            $this->line('  <fg=cyan>Current .env values:</>');
            if ($url) {
                $this->line("    COOLIFY_URL: <fg=green>{$url}</>");
            }
            if ($uuid) {
                $this->line("    COOLIFY_APPLICATION_UUID: <fg=green>{$uuid}</>");
            }
        }

        $this->newLine();
        info("Deployments will trigger on push to '{$branch}'.");

        return self::SUCCESS;
    }
}
