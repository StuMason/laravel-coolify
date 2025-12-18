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

            // Get environment IDs from the project
            // The API doesn't return project_uuid on resources, but it does return environment_id
            // We can use this to filter resources belonging to this project
            $projectEnvironmentIds = collect($project['environments'] ?? [])
                ->pluck('id')
                ->filter()
                ->all();

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

            // Filter to this project's resources by environment_id
            // (project_uuid is null in API responses, so we use environment_id instead)
            $projectApps = collect($allApps)->filter(fn ($app) => in_array($app['environment_id'] ?? null, $projectEnvironmentIds, true))->values()->all();

            $projectDatabases = collect($allDatabases)->filter(fn ($db) => in_array($db['environment_id'] ?? null, $projectEnvironmentIds, true))->values()->all();

            $projectServices = collect($allServices)->filter(fn ($svc) => in_array($svc['environment_id'] ?? null, $projectEnvironmentIds, true))->values()->all();

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

            // Re-fetch resources right before deletion to get fresh data
            // (API responses can be cached/stale between showing preview and actual deletion)
            $allApps = $applications->all();
            $allDatabases = $databases->all();
            $allServices = $services->all();

            $projectApps = collect($allApps)->filter(fn ($app) => in_array($app['environment_id'] ?? null, $projectEnvironmentIds, true))->values()->all();
            $projectDatabases = collect($allDatabases)->filter(fn ($db) => in_array($db['environment_id'] ?? null, $projectEnvironmentIds, true))->values()->all();
            $projectServices = collect($allServices)->filter(fn ($svc) => in_array($svc['environment_id'] ?? null, $projectEnvironmentIds, true))->values()->all();

            // Step 1: Stop all resources first
            foreach ($projectApps as $app) {
                $this->stopApplication($applications, $app);
            }

            foreach ($projectDatabases as $db) {
                $this->stopDatabase($databases, $db);
            }

            foreach ($projectServices as $svc) {
                $this->stopService($services, $svc);
            }

            // Brief pause to let Coolify process the stops
            if (count($projectApps) + count($projectDatabases) + count($projectServices) > 0) {
                sleep(2);
            }

            // Step 2: Delete applications
            foreach ($projectApps as $app) {
                $this->deleteApplication($applications, $app);
            }

            // Step 3: Delete databases
            foreach ($projectDatabases as $db) {
                $this->deleteDatabase($databases, $db);
            }

            // Step 4: Delete services
            foreach ($projectServices as $svc) {
                $this->deleteService($services, $svc);
            }

            // Step 5: Wait for all resources to be deleted before deleting project
            // Deletions are queued and async, so we need to poll until complete
            $resourceCount = count($projectApps) + count($projectDatabases) + count($projectServices);

            if ($resourceCount > 0) {
                $this->waitForResourceDeletion(
                    $applications,
                    $databases,
                    $services,
                    $projectApps,
                    $projectDatabases,
                    $projectServices
                );
            }

            // Step 6: Finally delete the project
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

    protected function stopApplication(ApplicationRepository $applications, array $app): void
    {
        try {
            spin(
                callback: fn () => $applications->stop($app['uuid']),
                message: "Stopping application '{$app['name']}'..."
            );
        } catch (CoolifyApiException) {
            // Ignore - might already be stopped
        }
    }

    protected function stopDatabase(DatabaseRepository $databases, array $db): void
    {
        try {
            spin(
                callback: fn () => $databases->stop($db['uuid']),
                message: "Stopping database '{$db['name']}'..."
            );
        } catch (CoolifyApiException) {
            // Ignore - might already be stopped
        }
    }

    protected function stopService(ServiceRepository $services, array $svc): void
    {
        try {
            spin(
                callback: fn () => $services->stop($svc['uuid']),
                message: "Stopping service '{$svc['name']}'..."
            );
        } catch (CoolifyApiException) {
            // Ignore - might already be stopped
        }
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

    /**
     * Wait for all resources to be fully deleted before deleting the project.
     * Deletions are queued and async, so we poll until they're gone.
     */
    protected function waitForResourceDeletion(
        ApplicationRepository $applications,
        DatabaseRepository $databases,
        ServiceRepository $services,
        array $projectApps,
        array $projectDatabases,
        array $projectServices
    ): void {
        $appUuids = collect($projectApps)->pluck('uuid')->all();
        $dbUuids = collect($projectDatabases)->pluck('uuid')->all();
        $svcUuids = collect($projectServices)->pluck('uuid')->all();

        $maxAttempts = 30; // 30 attempts * 2 seconds = 60 seconds max
        $attempt = 0;

        spin(
            callback: function () use ($applications, $databases, $services, $appUuids, $dbUuids, $svcUuids, $maxAttempts, &$attempt): bool {
                while ($attempt < $maxAttempts) {
                    $attempt++;

                    // Check if any apps still exist
                    $remainingApps = 0;
                    if (! empty($appUuids)) {
                        try {
                            $allApps = $applications->all();
                            $remainingApps = collect($allApps)->whereIn('uuid', $appUuids)->count();
                        } catch (CoolifyApiException) {
                            // Ignore errors during polling
                        }
                    }

                    // Check if any databases still exist
                    $remainingDbs = 0;
                    if (! empty($dbUuids)) {
                        try {
                            $allDbs = $databases->all();
                            $remainingDbs = collect($allDbs)->whereIn('uuid', $dbUuids)->count();
                        } catch (CoolifyApiException) {
                            // Ignore errors during polling
                        }
                    }

                    // Check if any services still exist
                    $remainingSvcs = 0;
                    if (! empty($svcUuids)) {
                        try {
                            $allSvcs = $services->all();
                            $remainingSvcs = collect($allSvcs)->whereIn('uuid', $svcUuids)->count();
                        } catch (CoolifyApiException) {
                            // Ignore errors during polling
                        }
                    }

                    // All resources deleted?
                    if ($remainingApps === 0 && $remainingDbs === 0 && $remainingSvcs === 0) {
                        return true;
                    }

                    sleep(2);
                }

                // Timed out but continue anyway - project delete will fail if resources remain
                return true;
            },
            message: 'Waiting for resources to be deleted...'
        );
    }
}
