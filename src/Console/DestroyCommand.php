<?php

namespace Stumason\Coolify\Console;

use Illuminate\Console\Command;
use Stumason\Coolify\Contracts\ApplicationRepository;
use Stumason\Coolify\Contracts\DatabaseRepository;
use Stumason\Coolify\Contracts\ProjectRepository;
use Stumason\Coolify\Contracts\ServiceRepository;
use Stumason\Coolify\CoolifyClient;
use Stumason\Coolify\Exceptions\CoolifyApiException;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

#[AsCommand(name: 'coolify:destroy')]
class DestroyCommand extends Command
{
    protected $signature = 'coolify:destroy
                            {--project= : Project UUID to destroy}
                            {--force : Skip confirmation}';

    protected $description = 'Destroy a Coolify project and all its resources (applications, databases, services)';

    protected int $deletedApps = 0;

    protected int $deletedDatabases = 0;

    protected int $deletedServices = 0;

    public function handle(
        CoolifyClient $client,
        ProjectRepository $projects,
        ApplicationRepository $applications,
        DatabaseRepository $databases,
        ServiceRepository $services
    ): int {
        if (! $client->isConfigured()) {
            error('Coolify is not configured. Please set COOLIFY_URL and COOLIFY_TOKEN in your .env file.');

            return self::FAILURE;
        }

        if (! $client->testConnection()) {
            error('Cannot connect to Coolify. Please check your configuration.');

            return self::FAILURE;
        }

        try {
            // Get project UUID
            $projectUuid = $this->option('project') ?? $this->selectProject($projects);

            if (! $projectUuid) {
                return self::FAILURE;
            }

            // Fetch project details
            $project = spin(
                callback: fn () => $projects->get($projectUuid),
                message: 'Fetching project details...'
            );

            $projectName = $project['name'] ?? $projectUuid;

            // Fetch all resources in the project
            $allApps = spin(
                callback: fn () => $applications->all(),
                message: 'Fetching applications...'
            );

            $allDatabases = spin(
                callback: fn () => $databases->all(),
                message: 'Fetching databases...'
            );

            $allServices = spin(
                callback: fn () => $services->all(),
                message: 'Fetching services...'
            );

            // Filter to this project's resources
            $projectApps = collect($allApps)->filter(fn ($app) => ($app['project_uuid'] ?? null) === $projectUuid)->values()->all();

            $projectDatabases = collect($allDatabases)->filter(fn ($db) => ($db['project_uuid'] ?? null) === $projectUuid)->values()->all();

            $projectServices = collect($allServices)->filter(fn ($svc) => ($svc['project_uuid'] ?? null) === $projectUuid)->values()->all();

            // Show what will be deleted
            $this->newLine();
            warning("This will permanently delete project: {$projectName}");
            $this->newLine();

            $this->components->twoColumnDetail('Applications', (string) count($projectApps));
            $this->components->twoColumnDetail('Databases', (string) count($projectDatabases));
            $this->components->twoColumnDetail('Services', (string) count($projectServices));

            if (count($projectApps) > 0) {
                $this->newLine();
                $this->line('  <comment>Applications:</comment>');
                foreach ($projectApps as $app) {
                    $this->line("    - {$app['name']} ({$app['uuid']})");
                }
            }

            if (count($projectDatabases) > 0) {
                $this->newLine();
                $this->line('  <comment>Databases:</comment>');
                foreach ($projectDatabases as $db) {
                    $this->line("    - {$db['name']} ({$db['uuid']})");
                }
            }

            if (count($projectServices) > 0) {
                $this->newLine();
                $this->line('  <comment>Services:</comment>');
                foreach ($projectServices as $svc) {
                    $this->line("    - {$svc['name']} ({$svc['uuid']})");
                }
            }

            $this->newLine();

            // Confirm deletion
            if (! $this->option('force') && ! $this->option('no-interaction')) {
                if (! confirm("Are you sure you want to delete project '{$projectName}' and ALL its resources?", false)) {
                    info('Destruction cancelled.');

                    return self::SUCCESS;
                }

                // Double confirm for safety
                if (! confirm('This action is IRREVERSIBLE. Type yes to confirm.', false)) {
                    info('Destruction cancelled.');

                    return self::SUCCESS;
                }
            }

            // Delete applications first
            foreach ($projectApps as $app) {
                $this->deleteApplication($applications, $app);
            }

            // Delete databases
            foreach ($projectDatabases as $db) {
                $this->deleteDatabase($databases, $db);
            }

            // Delete services
            foreach ($projectServices as $svc) {
                $this->deleteService($services, $svc);
            }

            // Finally delete the project
            spin(
                callback: fn () => $projects->delete($projectUuid),
                message: "Deleting project '{$projectName}'..."
            );

            $this->newLine();
            info('Destruction complete!');
            $this->newLine();

            $this->components->twoColumnDetail('Applications deleted', (string) $this->deletedApps);
            $this->components->twoColumnDetail('Databases deleted', (string) $this->deletedDatabases);
            $this->components->twoColumnDetail('Services deleted', (string) $this->deletedServices);
            $this->components->twoColumnDetail('Project deleted', $projectName);

        } catch (CoolifyApiException $exception) {
            error("Destruction failed: {$exception->getMessage()}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function selectProject(ProjectRepository $projects): ?string
    {
        // Check config first
        if ($uuid = config('coolify.project_uuid')) {
            return $uuid;
        }

        $projectList = spin(
            callback: fn () => $projects->all(),
            message: 'Fetching projects...'
        );

        if (empty($projectList)) {
            error('No projects found in your Coolify instance.');

            return null;
        }

        $choices = collect($projectList)->mapWithKeys(fn ($p) => [
            $p['uuid'] => $p['name'],
        ])->toArray();

        return select(
            label: 'Select project to destroy:',
            options: $choices
        );
    }

    protected function deleteApplication(ApplicationRepository $applications, array $app): void
    {
        try {
            spin(
                callback: fn () => $applications->delete($app['uuid']),
                message: "Deleting application '{$app['name']}'..."
            );
            $this->deletedApps++;
        } catch (CoolifyApiException $exception) {
            warning("Failed to delete application '{$app['name']}': {$exception->getMessage()}");
        }
    }

    protected function deleteDatabase(DatabaseRepository $databases, array $db): void
    {
        try {
            spin(
                callback: fn () => $databases->delete($db['uuid']),
                message: "Deleting database '{$db['name']}'..."
            );
            $this->deletedDatabases++;
        } catch (CoolifyApiException $exception) {
            warning("Failed to delete database '{$db['name']}': {$exception->getMessage()}");
        }
    }

    protected function deleteService(ServiceRepository $services, array $svc): void
    {
        try {
            spin(
                callback: fn () => $services->delete($svc['uuid']),
                message: "Deleting service '{$svc['name']}'..."
            );
            $this->deletedServices++;
        } catch (CoolifyApiException $exception) {
            warning("Failed to delete service '{$svc['name']}': {$exception->getMessage()}");
        }
    }
}
