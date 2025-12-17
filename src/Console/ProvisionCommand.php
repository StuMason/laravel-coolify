<?php

namespace Stumason\Coolify\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Stumason\Coolify\Contracts\ApplicationRepository;
use Stumason\Coolify\Contracts\DatabaseRepository;
use Stumason\Coolify\Contracts\GitHubAppRepository;
use Stumason\Coolify\Contracts\ProjectRepository;
use Stumason\Coolify\Contracts\ServerRepository;
use Stumason\Coolify\CoolifyClient;
use Stumason\Coolify\Exceptions\CoolifyApiException;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\search;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

#[AsCommand(name: 'coolify:provision')]
class ProvisionCommand extends Command
{
    protected $signature = 'coolify:provision
                            {--name= : Application name}
                            {--domain= : Application domain}
                            {--server= : Server UUID to deploy to}
                            {--project= : Project UUID}
                            {--environment=production : Environment name}
                            {--github-app= : GitHub App UUID}
                            {--repository= : GitHub repository (owner/repo)}
                            {--branch= : Git branch}
                            {--with-postgres : Create PostgreSQL database}
                            {--with-dragonfly : Create Dragonfly (Redis) instance}
                            {--with-redis : Create Redis instance}
                            {--all : Create app with Postgres and Dragonfly}
                            {--force : Skip confirmations}';

    protected $description = 'Provision a complete Laravel application stack on Coolify';

    protected array $createdResources = [];

    protected ?string $postgresInternalUrl = null;

    protected ?string $redisInternalUrl = null;

    public function handle(
        CoolifyClient $client,
        ServerRepository $servers,
        ProjectRepository $projects,
        ApplicationRepository $applications,
        DatabaseRepository $databases,
        GitHubAppRepository $githubApps
    ): int {
        if (! $client->isConfigured()) {
            $this->components->error('Coolify is not configured. Please set COOLIFY_URL and COOLIFY_TOKEN in your .env file.');

            return self::FAILURE;
        }

        if (! $client->testConnection()) {
            $this->components->error('Cannot connect to Coolify. Please check your configuration.');

            return self::FAILURE;
        }

        info('Provisioning Laravel Stack on Coolify');

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

            // Step 4: Select GitHub App and repository
            $githubAppUuid = $this->selectGitHubApp($githubApps);
            if (! $githubAppUuid) {
                return self::FAILURE;
            }

            $repoInfo = $this->selectRepository($githubApps, $githubAppUuid);
            if (! $repoInfo) {
                return self::FAILURE;
            }

            $branch = $this->selectBranch($githubApps, $githubAppUuid, $repoInfo['owner'], $repoInfo['repo']);
            if (! $branch) {
                return self::FAILURE;
            }

            // Step 5: Gather application details
            $appName = $this->option('name') ?? text(
                label: 'Application name',
                default: $repoInfo['repo'],
                required: true
            );

            $domain = $this->option('domain') ?? text(
                label: 'Application domain',
                placeholder: 'myapp.example.com',
                required: true
            );

            // Step 6: Determine which resources to create
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
                    "Application: {$appName} ({$repoInfo['full_name']}:{$branch})",
                    $withPostgres ? 'PostgreSQL database' : null,
                    $withDragonfly ? 'Dragonfly (Redis)' : null,
                    $withRedis ? 'Redis' : null,
                ]));

                if (! confirm('Proceed with provisioning?', true)) {
                    warning('Provisioning cancelled.');

                    return self::SUCCESS;
                }
            }

            // Step 7: Create PostgreSQL if requested
            $dbUuid = null;
            if ($withPostgres) {
                $dbUuid = $this->createPostgres($databases, $serverUuid, $projectUuid, $environment, $appName);
                if ($dbUuid) {
                    $this->waitForDatabase($databases, $dbUuid, 'PostgreSQL');
                }
            }

            // Step 8: Create Dragonfly/Redis if requested
            $redisUuid = null;
            if ($withDragonfly) {
                $redisUuid = $this->createDragonfly($databases, $serverUuid, $projectUuid, $environment, $appName);
                if ($redisUuid) {
                    $this->waitForDatabase($databases, $redisUuid, 'Dragonfly');
                }
            } elseif ($withRedis) {
                $redisUuid = $this->createRedis($databases, $serverUuid, $projectUuid, $environment, $appName);
                if ($redisUuid) {
                    $this->waitForDatabase($databases, $redisUuid, 'Redis');
                }
            }

            // Step 9: Create Application
            $appUuid = $this->createApplication(
                $applications,
                $serverUuid,
                $projectUuid,
                $environment,
                $appName,
                $domain,
                $githubAppUuid,
                $repoInfo['full_name'],
                $branch
            );

            if (! $appUuid) {
                throw new CoolifyApiException('Failed to create application');
            }

            // Step 10: Set environment variables on the application
            $this->setApplicationEnvVars($applications, $appUuid, $projectUuid, $dbUuid, $redisUuid, $databases);

            // Step 11: Update local .env file with UUIDs
            $this->updateEnvFile($projectUuid, $appUuid, $dbUuid, $redisUuid);

            // Success summary
            $this->newLine();
            $this->components->info('Provisioning complete!');
            $this->newLine();

            $this->components->twoColumnDetail('Project UUID', $projectUuid);
            $this->components->twoColumnDetail('Application UUID', $appUuid);
            if ($dbUuid) {
                $this->components->twoColumnDetail('Database UUID', $dbUuid);
            }
            if ($redisUuid) {
                $this->components->twoColumnDetail('Redis UUID', $redisUuid);
            }

            $this->newLine();
            $this->line('  Your .env file has been updated with the Coolify resource UUIDs.');
            $this->line('  Database connection environment variables have been set on your Coolify application.');
            $this->newLine();
            $this->line('  Run <comment>php artisan coolify:deploy</comment> to trigger your first deployment.');

        } catch (CoolifyApiException $exception) {
            $this->components->error("Provisioning failed: {$exception->getMessage()}");

            if (! empty($this->createdResources)) {
                warning('Note: Some resources may have been created before the failure.');
                $this->components->bulletList(
                    collect($this->createdResources)->map(fn ($uuid, $type) => "{$type}: {$uuid}")->toArray()
                );
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

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

        $existingProjects = collect($projectList)->mapWithKeys(fn ($p) => [
            $p['uuid'] => $p['name'],
        ])->toArray();

        // Put "Create new project" first
        $choices = ['__new__' => '+ Create new project'] + $existingProjects;

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

    protected function selectGitHubApp(GitHubAppRepository $githubApps): ?string
    {
        if ($uuid = $this->option('github-app')) {
            return $uuid;
        }

        // Check config for default GitHub App
        if ($uuid = config('coolify.github_app_uuid')) {
            return $uuid;
        }

        try {
            $appList = spin(
                callback: fn () => $githubApps->all(),
                message: 'Fetching GitHub Apps...'
            );
        } catch (CoolifyApiException) {
            $appList = [];
        }

        if (empty($appList)) {
            // API couldn't list GitHub Apps - allow manual entry
            warning('Could not fetch GitHub Apps from API.');
            $this->line('  This may be a permission issue with your API token.');
            $this->newLine();
            $this->line('  You can find your GitHub App UUID in Coolify:');
            $this->line('  Sources -> GitHub -> (select your app) -> copy the UUID from the URL');
            $this->newLine();

            $uuid = text(
                label: 'Enter your GitHub App UUID:',
                placeholder: 'e.g., owkswog8kksgsk4o0wccwc40',
                required: true,
                hint: 'Find this in the URL when viewing your GitHub source in Coolify'
            );

            return $uuid ?: null;
        }

        $choices = collect($appList)->mapWithKeys(fn ($app) => [
            $app['uuid'] => $app['name'] ?? $app['app_name'] ?? "GitHub App ({$app['uuid']})",
        ])->toArray();

        return select(
            label: 'Select GitHub App:',
            options: $choices
        );
    }

    protected function selectRepository(GitHubAppRepository $githubApps, string $githubAppUuid): ?array
    {
        if ($repo = $this->option('repository')) {
            [$owner, $repoName] = explode('/', $repo, 2);

            return [
                'owner' => $owner,
                'repo' => $repoName,
                'full_name' => $repo,
            ];
        }

        $repositories = spin(
            callback: fn () => $githubApps->repositories($githubAppUuid),
            message: 'Fetching repositories...'
        );

        if (empty($repositories)) {
            $this->components->error('No repositories found for this GitHub App.');

            return null;
        }

        // Build a searchable list
        $repoChoices = collect($repositories)->mapWithKeys(function ($repo) {
            $fullName = $repo['full_name'] ?? "{$repo['owner']['login']}/{$repo['name']}";

            return [$fullName => $fullName];
        })->toArray();

        $selected = search(
            label: 'Search and select repository:',
            options: fn (string $value) => collect($repoChoices)
                ->filter(fn ($name) => empty($value) || Str::contains(strtolower($name), strtolower($value)))
                ->toArray(),
            placeholder: 'Type to search...'
        );

        if (! $selected) {
            return null;
        }

        [$owner, $repoName] = explode('/', $selected, 2);

        return [
            'owner' => $owner,
            'repo' => $repoName,
            'full_name' => $selected,
        ];
    }

    protected function selectBranch(GitHubAppRepository $githubApps, string $githubAppUuid, string $owner, string $repo): ?string
    {
        if ($branch = $this->option('branch')) {
            return $branch;
        }

        $branches = spin(
            callback: fn () => $githubApps->branches($githubAppUuid, $owner, $repo),
            message: 'Fetching branches...'
        );

        if (empty($branches)) {
            return 'main';
        }

        $branchChoices = collect($branches)->mapWithKeys(fn ($b) => [
            $b['name'] => $b['name'],
        ])->toArray();

        // Put main/master at the top if they exist
        $sortedChoices = [];
        foreach (['main', 'master'] as $defaultBranch) {
            if (isset($branchChoices[$defaultBranch])) {
                $sortedChoices[$defaultBranch] = $defaultBranch;
            }
        }
        $sortedChoices = array_merge($sortedChoices, $branchChoices);

        return select(
            label: 'Select branch:',
            options: $sortedChoices,
            default: array_key_first($sortedChoices)
        );
    }

    protected function createPostgres(
        DatabaseRepository $databases,
        string $serverUuid,
        string $projectUuid,
        string $environment,
        string $appName
    ): ?string {
        $dbName = Str::snake(Str::lower($appName));

        $result = spin(
            callback: fn () => $databases->createPostgres([
                'server_uuid' => $serverUuid,
                'project_uuid' => $projectUuid,
                'environment_name' => $environment,
                'name' => "{$appName}-db",
                'postgres_user' => 'laravel',
                'postgres_db' => $dbName,
                'instant_deploy' => true,
            ]),
            message: 'Creating PostgreSQL database...'
        );

        $uuid = $result['uuid'] ?? null;

        if ($uuid) {
            $this->createdResources['PostgreSQL'] = $uuid;
        }

        return $uuid;
    }

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
                'instant_deploy' => true,
            ]),
            message: 'Creating Dragonfly instance...'
        );

        $uuid = $result['uuid'] ?? null;

        if ($uuid) {
            $this->createdResources['Dragonfly'] = $uuid;
        }

        return $uuid;
    }

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
                'instant_deploy' => true,
            ]),
            message: 'Creating Redis instance...'
        );

        $uuid = $result['uuid'] ?? null;

        if ($uuid) {
            $this->createdResources['Redis'] = $uuid;
        }

        return $uuid;
    }

    protected function waitForDatabase(DatabaseRepository $databases, string $uuid, string $type): void
    {
        $maxAttempts = 30;
        $attempt = 0;

        spin(
            callback: function () use ($databases, $uuid, $type, $maxAttempts, &$attempt): bool {
                while ($attempt < $maxAttempts) {
                    $attempt++;
                    $db = $databases->get($uuid);

                    $status = $db['status'] ?? 'unknown';

                    if ($status === 'running' || $status === 'healthy') {
                        // Store internal URL for later use
                        if ($type === 'PostgreSQL') {
                            $this->postgresInternalUrl = $db['internal_db_url'] ?? null;
                        } else {
                            $this->redisInternalUrl = $db['internal_db_url'] ?? null;
                        }

                        return true;
                    }

                    if ($status === 'error' || $status === 'failed') {
                        throw new CoolifyApiException("{$type} failed to start");
                    }

                    sleep(2);
                }

                throw new CoolifyApiException("{$type} did not become ready in time");
            },
            message: "Waiting for {$type} to be ready..."
        );
    }

    protected function createApplication(
        ApplicationRepository $applications,
        string $serverUuid,
        string $projectUuid,
        string $environment,
        string $appName,
        string $domain,
        string $githubAppUuid,
        string $gitRepository,
        string $branch
    ): ?string {
        $result = spin(
            callback: fn () => $applications->createPrivateGithubApp([
                'server_uuid' => $serverUuid,
                'project_uuid' => $projectUuid,
                'environment_name' => $environment,
                'github_app_uuid' => $githubAppUuid,
                'git_repository' => $gitRepository,
                'git_branch' => $branch,
                'build_pack' => 'nixpacks',
                'ports_exposes' => '8080',
                'name' => $appName,
                'domains' => "https://{$domain}",
                'instant_deploy' => false,
            ]),
            message: 'Creating application...'
        );

        $uuid = $result['uuid'] ?? null;

        if ($uuid) {
            $this->createdResources['Application'] = $uuid;
        }

        return $uuid;
    }

    protected function setApplicationEnvVars(
        ApplicationRepository $applications,
        string $appUuid,
        string $projectUuid,
        ?string $dbUuid,
        ?string $redisUuid,
        DatabaseRepository $databases
    ): void {
        $envVars = [];

        // Set Coolify resource UUIDs so they're available in production
        $envVars[] = ['key' => 'COOLIFY_PROJECT_UUID', 'value' => $projectUuid, 'is_build_time' => false];
        $envVars[] = ['key' => 'COOLIFY_APPLICATION_UUID', 'value' => $appUuid, 'is_build_time' => false];

        if ($dbUuid) {
            $envVars[] = ['key' => 'COOLIFY_DATABASE_UUID', 'value' => $dbUuid, 'is_build_time' => false];
        }

        if ($redisUuid) {
            $envVars[] = ['key' => 'COOLIFY_REDIS_UUID', 'value' => $redisUuid, 'is_build_time' => false];
        }

        // Fetch PostgreSQL connection details
        if ($dbUuid) {
            $db = $databases->get($dbUuid);
            $internalUrl = $db['internal_db_url'] ?? $this->postgresInternalUrl;

            if ($internalUrl) {
                $envVars[] = ['key' => 'DATABASE_URL', 'value' => $internalUrl, 'is_build_time' => false];
                $envVars[] = ['key' => 'DB_CONNECTION', 'value' => 'pgsql', 'is_build_time' => false];

                // Parse the URL to extract individual components
                $parsed = parse_url($internalUrl);
                if ($parsed) {
                    $envVars[] = ['key' => 'DB_HOST', 'value' => $parsed['host'] ?? '', 'is_build_time' => false];
                    $envVars[] = ['key' => 'DB_PORT', 'value' => (string) ($parsed['port'] ?? 5432), 'is_build_time' => false];
                    $envVars[] = ['key' => 'DB_DATABASE', 'value' => ltrim($parsed['path'] ?? '', '/'), 'is_build_time' => false];
                    $envVars[] = ['key' => 'DB_USERNAME', 'value' => $parsed['user'] ?? '', 'is_build_time' => false];
                    $envVars[] = ['key' => 'DB_PASSWORD', 'value' => $parsed['pass'] ?? '', 'is_build_time' => false];
                }
            }
        }

        // Fetch Redis/Dragonfly connection details
        if ($redisUuid) {
            $redis = $databases->get($redisUuid);
            $internalUrl = $redis['internal_db_url'] ?? $this->redisInternalUrl;

            if ($internalUrl) {
                $envVars[] = ['key' => 'REDIS_URL', 'value' => $internalUrl, 'is_build_time' => false];
                $envVars[] = ['key' => 'CACHE_STORE', 'value' => 'redis', 'is_build_time' => false];
                $envVars[] = ['key' => 'SESSION_DRIVER', 'value' => 'redis', 'is_build_time' => false];
                $envVars[] = ['key' => 'QUEUE_CONNECTION', 'value' => 'redis', 'is_build_time' => false];

                // Parse the URL for individual components
                $parsed = parse_url($internalUrl);
                if ($parsed) {
                    $envVars[] = ['key' => 'REDIS_HOST', 'value' => $parsed['host'] ?? '', 'is_build_time' => false];
                    $envVars[] = ['key' => 'REDIS_PORT', 'value' => (string) ($parsed['port'] ?? 6379), 'is_build_time' => false];
                    if (! empty($parsed['pass'])) {
                        $envVars[] = ['key' => 'REDIS_PASSWORD', 'value' => $parsed['pass'], 'is_build_time' => false];
                    }
                }
            }
        }

        spin(
            callback: function () use ($applications, $appUuid, $envVars): void {
                foreach ($envVars as $env) {
                    $applications->updateEnvs($appUuid, $env);
                }
            },
            message: 'Setting application environment variables...'
        );

        note('Environment variables have been set on your Coolify application.');
    }

    protected function updateEnvFile(string $projectUuid, ?string $appUuid, ?string $dbUuid, ?string $redisUuid): void
    {
        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            return;
        }

        $content = File::get($envPath);
        $updates = [];

        if (! str_contains($content, 'COOLIFY_PROJECT_UUID')) {
            $updates[] = "COOLIFY_PROJECT_UUID={$projectUuid}";
        }

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
