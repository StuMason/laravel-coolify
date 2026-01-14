<?php

namespace Stumason\Coolify\Console;

use Illuminate\Console\Command;
use Stumason\Coolify\Contracts\ApplicationRepository;
use Stumason\Coolify\Contracts\DatabaseRepository;
use Stumason\Coolify\CoolifyClient;
use Stumason\Coolify\Exceptions\CoolifyApiException;
use Stumason\Coolify\Models\CoolifyResource;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'coolify:status')]
class StatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coolify:status
                            {--uuid= : Application UUID (defaults to config)}
                            {--all : Show status of all resources}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get the current status of your Coolify resources';

    /**
     * Execute the console command.
     */
    public function handle(
        CoolifyClient $client,
        ApplicationRepository $applications,
        DatabaseRepository $databases
    ): int {
        if (! $client->isConfigured()) {
            $this->components->error('Coolify is not configured. Please set COOLIFY_URL and COOLIFY_TOKEN in your .env file.');

            return self::FAILURE;
        }

        // Test connection first
        if (! $client->testConnection()) {
            $this->components->error('Failed to connect to Coolify.');

            return self::FAILURE;
        }

        if ($this->option('all')) {
            return $this->showAllResources($applications, $databases);
        }

        return $this->showApplicationStatus($applications);
    }

    /**
     * Show status of all resources.
     */
    protected function showAllResources(
        ApplicationRepository $applications,
        DatabaseRepository $databases
    ): int {
        $this->newLine();
        $this->components->info('Applications');

        try {
            $apps = $applications->all();

            if (empty($apps)) {
                $this->components->warn('No applications found.');
            } else {
                $this->table(
                    ['UUID', 'Name', 'Status', 'FQDN'],
                    collect($apps)->map(fn ($app) => [
                        $app['uuid'] ?? 'N/A',
                        $app['name'] ?? 'N/A',
                        $this->formatStatus($app['status'] ?? 'unknown'),
                        $app['fqdn'] ?? 'N/A',
                    ])->toArray()
                );
            }
        } catch (CoolifyApiException $e) {
            $this->components->error("Failed to fetch applications: {$e->getMessage()}");
        }

        $this->newLine();
        $this->components->info('Databases');

        try {
            $dbs = $databases->all();

            if (empty($dbs)) {
                $this->components->warn('No databases found.');
            } else {
                $this->table(
                    ['UUID', 'Name', 'Type', 'Status'],
                    collect($dbs)->map(fn ($db) => [
                        $db['uuid'] ?? 'N/A',
                        $db['name'] ?? 'N/A',
                        $db['type'] ?? 'N/A',
                        $this->formatStatus($db['status'] ?? 'unknown'),
                    ])->toArray()
                );
            }
        } catch (CoolifyApiException $e) {
            $this->components->error("Failed to fetch databases: {$e->getMessage()}");
        }

        return self::SUCCESS;
    }

    /**
     * Show status of the configured application.
     */
    protected function showApplicationStatus(ApplicationRepository $applications): int
    {
        $uuid = $this->option('uuid') ?? CoolifyResource::getDefault()?->application_uuid;

        if (! $uuid) {
            $this->components->error('No application configured. Run coolify:provision first or use --uuid option.');

            return self::FAILURE;
        }

        try {
            $app = $applications->get($uuid);

            $this->newLine();
            $this->components->twoColumnDetail('Application', $app['name'] ?? 'N/A');
            $this->components->twoColumnDetail('UUID', $app['uuid'] ?? 'N/A');
            $this->components->twoColumnDetail('Status', $this->formatStatus($app['status'] ?? 'unknown'));
            $this->components->twoColumnDetail('FQDN', $app['fqdn'] ?? 'N/A');
            $this->components->twoColumnDetail('Repository', $app['git_repository'] ?? 'N/A');
            $this->components->twoColumnDetail('Branch', $app['git_branch'] ?? 'N/A');

            if (isset($app['git_commit_sha'])) {
                $this->components->twoColumnDetail('Commit', substr($app['git_commit_sha'], 0, 7));
            }

            $this->newLine();

            $status = strtolower($app['status'] ?? 'unknown');
            if ($status === 'running') {
                $this->components->info('Application is running.');
            } elseif ($status === 'stopped') {
                $this->components->warn('Application is stopped.');
            } else {
                $this->components->warn("Application status: {$status}");
            }

        } catch (CoolifyApiException $e) {
            $this->components->error("Failed to fetch application status: {$e->getMessage()}");

            return self::FAILURE;
        }

        return self::SUCCESS;
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
}
