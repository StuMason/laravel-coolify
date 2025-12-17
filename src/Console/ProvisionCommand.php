<?php

namespace Stumason\Coolify\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Stumason\Coolify\Contracts\ApplicationRepository;
use Stumason\Coolify\Contracts\DatabaseRepository;
use Stumason\Coolify\Contracts\ProjectRepository;
use Stumason\Coolify\Contracts\ServerRepository;
use Stumason\Coolify\CoolifyClient;
use Stumason\Coolify\Exceptions\CoolifyApiException;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

#[AsCommand(name: 'coolify:provision')]
class ProvisionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coolify:provision
                            {--name= : Application name}
                            {--domain= : Application domain}
                            {--server= : Server UUID to deploy to}
                            {--project= : Project UUID}
                            {--environment=production : Environment name}
                            {--with-postgres : Create PostgreSQL database}
                            {--with-dragonfly : Create Dragonfly (Redis) instance}
                            {--with-redis : Create Redis instance}
                            {--all : Create app with Postgres and Dragonfly}
                            {--force : Skip confirmations}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Provision a complete Laravel application stack on Coolify';

    protected array $createdResources = [];

    /**
     * Execute the console command.
     */
    public function handle(
        CoolifyClient $client,
        ServerRepository $servers,
        ProjectRepository $projects,
        ApplicationRepository $applications,
        DatabaseRepository $databases
    ): int {
        if (! $client->isConfigured()) {
            $this->components->error('Coolify is not configured. Please set COOLIFY_URL and COOLIFY_TOKEN in your .env file.');

            return self::FAILURE;
        }

        // Test connection
        if (! $client->testConnection()) {
            $this->components->error('Cannot connect to Coolify. Please check your configuration.');

            return self::FAILURE;
        }

        info('🚀 Provisioning Laravel Stack on Coolify');

        try {
            // Step 1: Get or select server
            $serverUuid = $this->selectServer($servers);
            if (! $serverUuid) {
                return self::FAILURE;
            }

            // Step 2: Get or select/create project
            $projectUuid = $this->selectProject($projects);
            if (! $projectUuid) {
                return self::FAILURE;
            }

            // Step 3: Get environment
            $environment = $this->option('environment') ?? 'production';

            // Step 4: Gather application details
            $appName = $this->option('name') ?? text(
                label: 'Application name',
                default: basename(base_path()),
                required: true
            );

            $domain = $this->option('domain') ?? text(
                label: 'Application domain',
                placeholder: 'myapp.example.com',
                required: true
            );

            // Step 5: Determine which resources to create
            $withPostgres = $this->option('all') || $this->option('with-postgres') ||
                (! $this->option('no-interaction') && confirm('Create PostgreSQL database?', true));

            $withDragonfly = $this->option('all') || $this->option('with-dragonfly') ||
                (! $this->option('no-interaction') && confirm('Create Dragonfly (Redis-compatible) instance?', true));

            $withRedis = $this->option('with-redis');

            // Confirm before proceeding
            if (! $this->option('force') && ! $this->option('no-interaction')) {
                $this->newLine();
                $this->components->info('Resources to create:');
                $this->components->bulletList(array_filter([
                    "Application: {$appName}",
                    $withPostgres ? 'PostgreSQL database' : null,
                    $withDragonfly ? 'Dragonfly (Redis)' : null,
                    $withRedis ? 'Redis' : null,
                ]));

                if (! confirm('Proceed with provisioning?', true)) {
                    warning('Provisioning cancelled.');

                    return self::SUCCESS;
                }
            }

            // Step 6: Create PostgreSQL if requested
            $dbUuid = null;
            if ($withPostgres) {
                $dbUuid = $this->createPostgres($databases, $serverUuid, $projectUuid, $environment, $appName);
            }

            // Step 7: Create Dragonfly/Redis if requested
            $redisUuid = null;
            if ($withDragonfly) {
                $redisUuid = $this->createDragonfly($databases, $serverUuid, $projectUuid, $environment, $appName);
            } elseif ($withRedis) {
                $redisUuid = $this->createRedis($databases, $serverUuid, $projectUuid, $environment, $appName);
            }

            // Step 8: Create Application
            $appUuid = $this->createApplication($applications, $serverUuid, $projectUuid, $environment, $appName, $domain);

            // Step 9: Update .env with UUIDs
            $this->updateEnvFile($appUuid, $dbUuid, $redisUuid);

            // Success summary
            $this->newLine();
            $this->components->info('✅ Provisioning complete!');
            $this->newLine();

            $this->components->twoColumnDetail('Application UUID', $appUuid ?? 'N/A');
            if ($dbUuid) {
                $this->components->twoColumnDetail('Database UUID', $dbUuid);
            }
            if ($redisUuid) {
                $this->components->twoColumnDetail('Redis UUID', $redisUuid);
            }

            $this->newLine();
            $this->line('  Your .env file has been updated with the Coolify UUIDs.');
            $this->line('  Run <comment>php artisan coolify:deploy</comment> to trigger your first deployment.');

        } catch (CoolifyApiException $e) {
            $this->components->error("Provisioning failed: {$e->getMessage()}");

            if (! empty($this->createdResources)) {
                warning('Note: Some resources may have been created before the failure.');
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Select or get server UUID.
     */
    protected function selectServer(ServerRepository $servers): ?string
    {
        if ($uuid = $this->option('server')) {
            return $uuid;
        }

        if ($uuid = config('coolify.server_uuid')) {
            return $uuid;
        }

        $serverList = spin(
            callback: fn () => $servers->all(),
            message: 'Fetching servers...'
        );

        if (empty($serverList)) {
            $this->components->error('No servers found in your Coolify instance.');

            return null;
        }

        $choices = collect($serverList)->mapWithKeys(fn ($s) => [
            $s['uuid'] => "{$s['name']} ({$s['ip']})",
        ])->toArray();

        return select(
            label: 'Select server to deploy to:',
            options: $choices
        );
    }

    /**
     * Select or create project.
     */
    protected function selectProject(ProjectRepository $projects): ?string
    {
        if ($uuid = $this->option('project')) {
            return $uuid;
        }

        if ($uuid = config('coolify.project_uuid')) {
            return $uuid;
        }

        $projectList = spin(
            callback: fn () => $projects->all(),
            message: 'Fetching projects...'
        );

        $choices = collect($projectList)->mapWithKeys(fn ($p) => [
            $p['uuid'] => $p['name'],
        ])->toArray();

        $choices['__new__'] = '+ Create new project';

        $selected = select(
            label: 'Select project:',
            options: $choices
        );

        if ($selected === '__new__') {
            $name = text(
                label: 'New project name',
                required: true
            );

            $result = spin(
                callback: fn () => $projects->create(['name' => $name]),
                message: 'Creating project...'
            );

            return $result['uuid'] ?? null;
        }

        return $selected;
    }

    /**
     * Create PostgreSQL database.
     */
    protected function createPostgres(
        DatabaseRepository $databases,
        string $serverUuid,
        string $projectUuid,
        string $environment,
        string $appName
    ): ?string {
        $result = spin(
            callback: fn () => $databases->createPostgres([
                'server_uuid' => $serverUuid,
                'project_uuid' => $projectUuid,
                'environment_name' => $environment,
                'name' => "{$appName}-db",
                'postgres_user' => 'laravel',
                'postgres_db' => str_replace('-', '_', $appName),
            ]),
            message: 'Creating PostgreSQL database...'
        );

        $uuid = $result['uuid'] ?? null;

        if ($uuid) {
            $this->createdResources['database'] = $uuid;
        }

        return $uuid;
    }

    /**
     * Create Dragonfly instance.
     */
    protected function createDragonfly(
        DatabaseRepository $databases,
        string $serverUuid,
        string $projectUuid,
        string $environment,
        string $appName
    ): ?string {
        $result = spin(
            callback: fn () => $databases->createDragonfly([
                'server_uuid' => $serverUuid,
                'project_uuid' => $projectUuid,
                'environment_name' => $environment,
                'name' => "{$appName}-cache",
            ]),
            message: 'Creating Dragonfly instance...'
        );

        $uuid = $result['uuid'] ?? null;

        if ($uuid) {
            $this->createdResources['redis'] = $uuid;
        }

        return $uuid;
    }

    /**
     * Create Redis instance.
     */
    protected function createRedis(
        DatabaseRepository $databases,
        string $serverUuid,
        string $projectUuid,
        string $environment,
        string $appName
    ): ?string {
        $result = spin(
            callback: fn () => $databases->createRedis([
                'server_uuid' => $serverUuid,
                'project_uuid' => $projectUuid,
                'environment_name' => $environment,
                'name' => "{$appName}-cache",
            ]),
            message: 'Creating Redis instance...'
        );

        $uuid = $result['uuid'] ?? null;

        if ($uuid) {
            $this->createdResources['redis'] = $uuid;
        }

        return $uuid;
    }

    /**
     * Create application.
     */
    protected function createApplication(
        ApplicationRepository $applications,
        string $serverUuid,
        string $projectUuid,
        string $environment,
        string $appName,
        string $domain
    ): ?string {
        // For now, we'll create a placeholder - the full implementation
        // would need to know the git repository details
        $result = spin(
            callback: fn () => $applications->create([
                'server_uuid' => $serverUuid,
                'project_uuid' => $projectUuid,
                'environment_name' => $environment,
                'name' => $appName,
                'fqdn' => "https://{$domain}",
                'build_pack' => 'nixpacks',
            ]),
            message: 'Creating application...'
        );

        $uuid = $result['uuid'] ?? null;

        if ($uuid) {
            $this->createdResources['application'] = $uuid;
        }

        return $uuid;
    }

    /**
     * Update .env file with created resource UUIDs.
     */
    protected function updateEnvFile(?string $appUuid, ?string $dbUuid, ?string $redisUuid): void
    {
        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            return;
        }

        $content = File::get($envPath);
        $updates = [];

        if ($appUuid && ! str_contains($content, 'COOLIFY_APPLICATION_UUID')) {
            $updates[] = "COOLIFY_APPLICATION_UUID={$appUuid}";
        }

        if ($dbUuid && ! str_contains($content, 'COOLIFY_DATABASE_UUID')) {
            $updates[] = "COOLIFY_DATABASE_UUID={$dbUuid}";
        }

        if ($redisUuid && ! str_contains($content, 'COOLIFY_REDIS_UUID')) {
            $updates[] = "COOLIFY_REDIS_UUID={$redisUuid}";
        }

        if (! empty($updates)) {
            $content .= "\n# Coolify Resources\n".implode("\n", $updates)."\n";
            File::put($envPath, $content);
        }
    }
}
