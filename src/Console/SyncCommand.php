<?php

namespace Stumason\Coolify\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Stumason\Coolify\Coolify;
use Stumason\Coolify\CoolifyClient;
use Stumason\Coolify\Exceptions\CoolifyApiException;
use Stumason\Coolify\Services\CoolifyProjectService;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

#[AsCommand(name: 'coolify:sync')]
class SyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coolify:sync
                            {--write-env : Write discovered UUIDs to .env file}
                            {--clear-cache : Clear cached project data}
                            {--show : Show discovered resources without prompting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Discover and sync Coolify project resources';

    /**
     * Execute the console command.
     */
    public function handle(CoolifyClient $client, CoolifyProjectService $projectService): int
    {
        if (! $client->isConfigured()) {
            $this->components->error('Coolify is not configured. Please set COOLIFY_URL and COOLIFY_TOKEN in your .env file.');

            return self::FAILURE;
        }

        // Test connection first
        if (! $client->testConnection()) {
            $this->components->error('Failed to connect to Coolify. Check your COOLIFY_URL and COOLIFY_TOKEN.');

            return self::FAILURE;
        }

        // Clear cache if requested
        if ($this->option('clear-cache')) {
            $projectService->clearCache();
            $this->components->info('Cache cleared.');

            if (! $this->option('show') && ! $this->option('write-env')) {
                return self::SUCCESS;
            }
        }

        $this->newLine();
        $this->components->info('Syncing Coolify Resources');

        // Check if project UUID is configured
        $projectUuid = $projectService->getProjectUuid();

        if (! $projectUuid) {
            // Help user discover their project UUID
            return $this->discoverProject($client, $projectService);
        }

        // Show discovered resources
        return $this->showResources($projectService);
    }

    /**
     * Help user discover their project UUID.
     */
    protected function discoverProject(CoolifyClient $client, CoolifyProjectService $projectService): int
    {
        $this->components->warn('COOLIFY_PROJECT_UUID is not set.');
        $this->newLine();

        try {
            // Fetch all projects
            $projects = spin(
                callback: fn () => Coolify::projects()->all(),
                message: 'Fetching projects from Coolify...'
            );

            if (empty($projects)) {
                $this->components->error('No projects found in your Coolify instance.');

                return self::FAILURE;
            }

            // Let user select a project
            $choices = collect($projects)->mapWithKeys(fn ($p) => [
                $p['uuid'] => $p['name'].' ('.$p['uuid'].')',
            ])->toArray();

            $selectedUuid = select(
                label: 'Select your project:',
                options: $choices
            );

            $selectedProject = collect($projects)->firstWhere('uuid', $selectedUuid);

            // Show environments for the selected project
            $environments = spin(
                callback: fn () => Coolify::projects()->environments($selectedUuid),
                message: 'Fetching environments...'
            );

            $environmentName = 'production';
            if (! empty($environments)) {
                $envChoices = collect($environments)->mapWithKeys(fn ($e) => [
                    $e['name'] => $e['name'],
                ])->toArray();

                if (count($envChoices) > 1) {
                    $environmentName = select(
                        label: 'Select environment:',
                        options: $envChoices,
                        default: isset($envChoices['production']) ? 'production' : array_key_first($envChoices)
                    );
                } else {
                    $environmentName = array_key_first($envChoices) ?? 'production';
                }
            }

            $this->newLine();
            $this->line('  <fg=cyan;options=bold>Add these to your .env file:</>');
            $this->newLine();
            $this->line("  <fg=white>COOLIFY_PROJECT_UUID=</><fg=gray>{$selectedUuid}</>");
            $this->line("  <fg=white>COOLIFY_ENVIRONMENT=</><fg=gray>{$environmentName}</>");
            $this->newLine();

            // Offer to write to .env
            if ($this->option('write-env') || (! $this->option('no-interaction') && confirm('Write these values to your .env file?', default: true))) {
                $this->writeToEnv([
                    'COOLIFY_PROJECT_UUID' => $selectedUuid,
                    'COOLIFY_ENVIRONMENT' => $environmentName,
                ]);
                $this->components->info('Values written to .env file.');
                $this->newLine();
                $this->line('  <fg=gray>Run</> <fg=cyan>php artisan coolify:sync --show</> <fg=gray>to see discovered resources.</>');
            }

            return self::SUCCESS;

        } catch (CoolifyApiException $e) {
            $this->components->error("Failed to fetch projects: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Show discovered resources from the configured project.
     */
    protected function showResources(CoolifyProjectService $projectService): int
    {
        try {
            $summary = spin(
                callback: fn () => $projectService->getResourceSummary(),
                message: 'Discovering resources...'
            );

            $this->newLine();
            $this->line('  <fg=cyan;options=bold>PROJECT CONFIGURATION</>');
            $this->components->twoColumnDetail('  Project UUID', $summary['project_uuid'] ?? 'N/A');
            $this->components->twoColumnDetail('  Environment', $summary['environment'] ?? 'N/A');

            $this->newLine();
            $this->line('  <fg=cyan;options=bold>DISCOVERED RESOURCES</>');
            $this->components->twoColumnDetail('  Application UUID', $summary['application_uuid'] ?? '<fg=yellow>Not found</>');
            $this->components->twoColumnDetail('  Database UUID', $summary['database_uuid'] ?? '<fg=gray>Not found</>');
            $this->components->twoColumnDetail('  Redis UUID', $summary['redis_uuid'] ?? '<fg=gray>Not found</>');
            $this->components->twoColumnDetail('  Server UUID', $summary['server_uuid'] ?? '<fg=gray>Not found</>');

            // Show applications
            if (! empty($summary['applications'])) {
                $this->newLine();
                $this->line('  <fg=yellow>Applications:</>');
                foreach ($summary['applications'] as $app) {
                    $status = $this->formatStatus($app['status'] ?? 'unknown');
                    $this->line("    • {$app['name']} ({$app['uuid']}) {$status}");
                }
            }

            // Show databases
            if (! empty($summary['databases'])) {
                $this->newLine();
                $this->line('  <fg=yellow>Databases:</>');
                foreach ($summary['databases'] as $db) {
                    $this->line("    • {$db['name']} ({$db['type']}) - {$db['uuid']}");
                }
            }

            // Show services
            if (! empty($summary['services'])) {
                $this->newLine();
                $this->line('  <fg=yellow>Services:</>');
                foreach ($summary['services'] as $service) {
                    $this->line("    • {$service['name']} - {$service['uuid']}");
                }
            }

            // Offer to write explicit UUIDs to .env
            if ($this->option('write-env') || (! $this->option('no-interaction') && ! $this->option('show') && confirm('Write discovered UUIDs to your .env file?', default: false))) {
                $envVars = [];

                if ($summary['application_uuid']) {
                    $envVars['COOLIFY_APPLICATION_UUID'] = $summary['application_uuid'];
                }
                if ($summary['database_uuid']) {
                    $envVars['COOLIFY_DATABASE_UUID'] = $summary['database_uuid'];
                }
                if ($summary['redis_uuid']) {
                    $envVars['COOLIFY_REDIS_UUID'] = $summary['redis_uuid'];
                }
                if ($summary['server_uuid']) {
                    $envVars['COOLIFY_SERVER_UUID'] = $summary['server_uuid'];
                }

                if (! empty($envVars)) {
                    $this->writeToEnv($envVars);
                    $this->newLine();
                    $this->components->info('UUIDs written to .env file.');
                }
            }

            $this->newLine();

            return self::SUCCESS;

        } catch (CoolifyApiException $e) {
            $this->components->error("Failed to discover resources: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Format status with color indicator.
     */
    protected function formatStatus(string $status): string
    {
        return match (strtolower($status)) {
            'running' => '<fg=green>● running</>',
            'stopped' => '<fg=red>● stopped</>',
            'starting' => '<fg=yellow>● starting</>',
            'restarting' => '<fg=yellow>● restarting</>',
            'deploying' => '<fg=blue>● deploying</>',
            default => "<fg=gray>● {$status}</>",
        };
    }

    /**
     * Write key-value pairs to the .env file.
     *
     * @param  array<string, string>  $values
     */
    protected function writeToEnv(array $values): void
    {
        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            // Create .env from .env.example if it exists
            $examplePath = base_path('.env.example');
            if (File::exists($examplePath)) {
                File::copy($examplePath, $envPath);
            } else {
                File::put($envPath, '');
            }
        }

        $content = File::get($envPath);

        foreach ($values as $key => $value) {
            // Check if key already exists
            if (preg_match("/^{$key}=.*/m", $content)) {
                // Update existing value
                $content = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $content);
            } else {
                // Add new value at the end
                $content = rtrim($content)."\n{$key}={$value}\n";
            }
        }

        File::put($envPath, $content);
    }
}
