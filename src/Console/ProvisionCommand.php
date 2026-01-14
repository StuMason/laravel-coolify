<?php

namespace Stumason\Coolify\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Stumason\Coolify\Console\Concerns\StreamsDeploymentLogs;
use Stumason\Coolify\Contracts\ApplicationRepository;
use Stumason\Coolify\Contracts\DatabaseRepository;
use Stumason\Coolify\Contracts\DeploymentRepository;
use Stumason\Coolify\Contracts\GitHubAppRepository;
use Stumason\Coolify\Contracts\ProjectRepository;
use Stumason\Coolify\Contracts\SecurityKeyRepository;
use Stumason\Coolify\Contracts\ServerRepository;
use Stumason\Coolify\CoolifyClient;
use Stumason\Coolify\Exceptions\CoolifyApiException;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\note;
use function Laravel\Prompts\pause;
use function Laravel\Prompts\search;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

#[AsCommand(name: 'coolify:provision')]
class ProvisionCommand extends Command
{
    use StreamsDeploymentLogs;

    protected $signature = 'coolify:provision
                            {--name= : Application name}
                            {--domain= : Application domain}
                            {--server= : Server UUID to deploy to}
                            {--project= : Project UUID}
                            {--environment=production : Environment name}
                            {--github-app= : GitHub App UUID (optional, for listing repos)}
                            {--repository= : GitHub repository (owner/repo)}
                            {--branch= : Git branch}
                            {--with-postgres : Create PostgreSQL database}
                            {--with-dragonfly : Create Dragonfly (Redis) instance}
                            {--with-redis : Create Redis instance}
                            {--all : Create app with Postgres and Dragonfly}
                            {--deploy : Trigger first deployment after provisioning}
                            {--force : Skip confirmations}';

    protected $description = 'Provision a complete Laravel application stack on Coolify';

    protected array $createdResources = [];

    protected ?string $postgresInternalUrl = null;

    protected ?string $redisInternalUrl = null;

    /**
     * Save a key-value pair to the .env file immediately.
     */
    protected function saveToEnv(string $key, string $value): void
    {
        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            return;
        }

        $content = File::get($envPath);

        // Check if the key already exists
        if (preg_match("/^{$key}=/m", $content)) {
            // Update existing key
            $content = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $content);
        } else {
            // Add new key (with a Coolify section header if needed)
            if (! str_contains($content, '# Coolify Resources')) {
                $content .= "\n# Coolify Resources\n";
            }
            $content .= "{$key}={$value}\n";
        }

        File::put($envPath, $content);
    }

    public function handle(
        CoolifyClient $client,
        ServerRepository $servers,
        ProjectRepository $projects,
        ApplicationRepository $applications,
        DatabaseRepository $databases,
        SecurityKeyRepository $securityKeys,
        GitHubAppRepository $githubApps,
        DeploymentRepository $deployments
    ): int {
        if (! $client->isConfigured()) {
            $this->components->error('Coolify is not configured.');
            $this->newLine();
            $this->line('  <fg=yellow;options=bold>SETUP REQUIRED:</>');
            $this->newLine();
            $this->line('  Add to your <fg=cyan>.env</> file:');
            $this->newLine();
            $this->line('    <fg=white>COOLIFY_URL=</><fg=gray>https://your-coolify-instance.com</>');
            $this->line('    <fg=white>COOLIFY_TOKEN=</><fg=gray>your-api-token</>');
            $this->newLine();
            $this->line('  <fg=yellow;options=bold>TO GET YOUR API TOKEN:</>');
            $this->newLine();
            $this->line('  1. Go to your Coolify dashboard');
            $this->line('  2. Navigate to <fg=cyan>Security → API Tokens</>');
            $this->line('  3. Create a new token (must be root-level, not team-level)');
            $this->newLine();

            return self::FAILURE;
        }

        if (! $client->testConnection()) {
            $this->components->error('Cannot connect to Coolify.');
            $this->newLine();
            $this->line('  <fg=yellow>Please check your configuration:</>');
            $this->newLine();
            $this->line('    <fg=white>COOLIFY_URL=</><fg=gray>'.config('coolify.url', '(not set)').'</>');
            $this->line('    <fg=white>COOLIFY_TOKEN=</><fg=gray>'.(config('coolify.token') ? '****'.substr(config('coolify.token'), -4) : '(not set)').'</>');
            $this->newLine();
            $this->line('  <fg=gray>Ensure your Coolify instance is accessible and the token is valid.</>');
            $this->newLine();

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('Provisioning Laravel Stack on Coolify');
        $this->newLine();

        // ─────────────────────────────────────────────────────────────────
        // PRE-FLIGHT CHECKS
        // ─────────────────────────────────────────────────────────────────
        if (! $this->runPreflightChecks()) {
            return self::FAILURE;
        }

        try {
            // ─────────────────────────────────────────────────────────────────
            // STEP 1: Select Server
            // ─────────────────────────────────────────────────────────────────
            $this->step(1, 'Select Server');

            $serverUuid = $this->selectServer($servers);
            if (! $serverUuid) {
                return self::FAILURE;
            }
            $this->saveToEnv('COOLIFY_SERVER_UUID', $serverUuid);
            $this->done("Server selected: {$serverUuid}");

            // ─────────────────────────────────────────────────────────────────
            // STEP 2: Select or Create Project
            // ─────────────────────────────────────────────────────────────────
            $this->step(2, 'Select or Create Project');

            $projectUuid = $this->selectProject($projects);
            if (! $projectUuid) {
                return self::FAILURE;
            }
            $this->saveToEnv('COOLIFY_PROJECT_UUID', $projectUuid);
            $this->done("Project selected: {$projectUuid}");

            // ─────────────────────────────────────────────────────────────────
            // STEP 3: Set Environment
            // ─────────────────────────────────────────────────────────────────
            $this->step(3, 'Set Environment');

            $environment = $this->option('environment') ?? 'production';
            $this->saveToEnv('COOLIFY_ENVIRONMENT', $environment);
            $this->done("Environment: {$environment}");

            // ─────────────────────────────────────────────────────────────────
            // STEP 4: Select Deploy Key
            // ─────────────────────────────────────────────────────────────────
            $this->step(4, 'Select Deploy Key');

            $deployKey = $this->selectDeployKey($securityKeys);
            if (! $deployKey) {
                return self::FAILURE;
            }
            $this->saveToEnv('COOLIFY_DEPLOY_KEY_UUID', $deployKey['uuid']);
            $this->done("Deploy Key: {$deployKey['name']}");

            // ─────────────────────────────────────────────────────────────────
            // STEP 5: Select Repository
            // ─────────────────────────────────────────────────────────────────
            $this->step(5, 'Select Repository');

            // Try to use GitHub App for listing repos (optional, may be rate limited)
            $githubApp = $this->getOptionalGitHubApp($githubApps);
            $repoInfo = $this->selectRepository($githubApps, $githubApp);
            if (! $repoInfo) {
                return self::FAILURE;
            }
            $this->saveToEnv('COOLIFY_REPOSITORY', $repoInfo['full_name']);
            $this->done("Repository: {$repoInfo['full_name']}");

            // ─────────────────────────────────────────────────────────────────
            // STEP 6: Select Branch
            // ─────────────────────────────────────────────────────────────────
            $this->step(6, 'Select Branch');

            $branch = $this->selectBranch($githubApps, $githubApp, $repoInfo['owner'], $repoInfo['repo']);
            if (! $branch) {
                return self::FAILURE;
            }
            $this->saveToEnv('COOLIFY_BRANCH', $branch);
            $this->done("Branch: {$branch}");

            // ─────────────────────────────────────────────────────────────────
            // STEP 7: Configure Resources
            // ─────────────────────────────────────────────────────────────────
            $this->step(7, 'Configure Resources');

            $withPostgres = $this->option('all') || $this->option('with-postgres') ||
                (! $this->option('no-interaction') && confirm('Create PostgreSQL database?', true));

            $withDragonfly = $this->option('all') || $this->option('with-dragonfly') ||
                (! $this->option('no-interaction') && confirm('Create Dragonfly (Redis-compatible) instance?', true));

            $withRedis = $this->option('with-redis');

            $appName = $this->option('name') ?? text(
                label: 'Application name',
                default: $repoInfo['repo'],
                required: true,
                validate: fn (string $value) => $this->validateResourceName($value)
            );

            $domain = $this->option('domain') ?? text(
                label: 'Application domain',
                placeholder: 'myapp.example.com',
                required: true,
                validate: fn (string $value) => $this->validateDomain($value)
            );

            $this->done('Configuration collected');

            // ─────────────────────────────────────────────────────────────────
            // CONFIRMATION
            // ─────────────────────────────────────────────────────────────────
            if (! $this->option('force') && ! $this->option('no-interaction')) {
                $this->newLine();
                $this->line('  <fg=yellow>Resources to be created:</>');
                $this->line("    - Application: <fg=cyan>{$appName}</> ({$repoInfo['full_name']}:{$branch})");
                $this->line("    - Domain: <fg=cyan>https://{$domain}</>");
                if ($withPostgres) {
                    $this->line('    - PostgreSQL database');
                }
                if ($withDragonfly) {
                    $this->line('    - Dragonfly (Redis-compatible cache)');
                }
                if ($withRedis) {
                    $this->line('    - Redis');
                }
                $this->newLine();

                if (! confirm('Proceed with provisioning?', true)) {
                    warning('Provisioning cancelled.');

                    return self::SUCCESS;
                }
            }

            // ─────────────────────────────────────────────────────────────────
            // STEP 8: Create Databases (run in background while app creates)
            // ─────────────────────────────────────────────────────────────────
            $dbUuid = null;
            $redisUuid = null;
            $cacheType = null;

            if ($withPostgres || $withDragonfly || $withRedis) {
                $this->step(8, 'Create Databases');
            }

            if ($withPostgres) {
                $this->line('    Creating PostgreSQL...');
                $dbUuid = $this->createPostgres($databases, $serverUuid, $projectUuid, $environment, $appName);
                if ($dbUuid) {
                    $this->saveToEnv('COOLIFY_DATABASE_UUID', $dbUuid);
                    $this->line("    <fg=green>PostgreSQL created:</> {$dbUuid}");
                }
            }

            if ($withDragonfly) {
                $this->line('    Creating Dragonfly...');
                $redisUuid = $this->createDragonfly($databases, $serverUuid, $projectUuid, $environment, $appName);
                $cacheType = 'Dragonfly';
                if ($redisUuid) {
                    $this->saveToEnv('COOLIFY_REDIS_UUID', $redisUuid);
                    $this->line("    <fg=green>Dragonfly created:</> {$redisUuid}");
                }
            } elseif ($withRedis) {
                $this->line('    Creating Redis...');
                $redisUuid = $this->createRedis($databases, $serverUuid, $projectUuid, $environment, $appName);
                $cacheType = 'Redis';
                if ($redisUuid) {
                    $this->saveToEnv('COOLIFY_REDIS_UUID', $redisUuid);
                    $this->line("    <fg=green>Redis created:</> {$redisUuid}");
                }
            }

            if ($withPostgres || $withDragonfly || $withRedis) {
                $this->done('Databases created (booting in background)');
            }

            // ─────────────────────────────────────────────────────────────────
            // STEP 9: Create Application
            // ─────────────────────────────────────────────────────────────────
            $stepNum = ($withPostgres || $withDragonfly || $withRedis) ? 9 : 8;
            $this->step($stepNum, 'Create Application');

            $appUuid = $this->createApplication(
                $applications,
                $serverUuid,
                $projectUuid,
                $environment,
                $appName,
                $domain,
                $deployKey['uuid'],
                $repoInfo['full_name'],
                $branch
            );

            if (! $appUuid) {
                throw new CoolifyApiException('Failed to create application');
            }
            $this->saveToEnv('COOLIFY_APPLICATION_UUID', $appUuid);
            $this->done("Application created: {$appUuid}");

            // ─────────────────────────────────────────────────────────────────
            // STEP 10: Wait for Databases
            // ─────────────────────────────────────────────────────────────────
            if ($dbUuid || $redisUuid) {
                $this->step($stepNum + 1, 'Wait for Databases to be Ready');

                if ($dbUuid) {
                    $this->line('    Checking PostgreSQL status...');
                    $this->waitForDatabase($databases, $dbUuid, 'PostgreSQL');
                    $this->line('    <fg=green>PostgreSQL is ready</>');
                }
                if ($redisUuid && $cacheType) {
                    $this->line("    Checking {$cacheType} status...");
                    $this->waitForDatabase($databases, $redisUuid, $cacheType);
                    $this->line("    <fg=green>{$cacheType} is ready</>");
                }

                $this->done('All databases ready');
            }

            // ─────────────────────────────────────────────────────────────────
            // STEP 11: Configure Environment Variables
            // ─────────────────────────────────────────────────────────────────
            $envStepNum = ($dbUuid || $redisUuid) ? $stepNum + 2 : $stepNum + 1;
            $this->step($envStepNum, 'Configure Application Environment Variables');

            $this->setApplicationEnvVars(
                $applications,
                $appUuid,
                $projectUuid,
                $dbUuid,
                $redisUuid,
                $databases,
                $serverUuid,
                $environment,
                $deployKey['uuid'],
                $repoInfo['full_name'],
                $branch,
                $appName,
                $domain
            );
            $this->done('Environment variables configured');

            // ─────────────────────────────────────────────────────────────────
            // SUCCESS SUMMARY
            // ─────────────────────────────────────────────────────────────────
            $this->newLine();
            $this->line('  <fg=green;options=bold>PROVISIONING COMPLETE!</>');
            $this->newLine();

            $this->line('  <fg=yellow>Created Resources:</>');
            $this->components->twoColumnDetail('  Project', $projectUuid);
            $this->components->twoColumnDetail('  Application', $appUuid);
            if ($dbUuid) {
                $this->components->twoColumnDetail('  PostgreSQL', $dbUuid);
            }
            if ($redisUuid) {
                $this->components->twoColumnDetail('  '.$cacheType, $redisUuid);
            }

            $this->newLine();
            $this->line('  <fg=gray>.env file updated with Coolify UUIDs</>');
            $this->line('  <fg=gray>Database credentials set on Coolify application</>');

            // ─────────────────────────────────────────────────────────────────
            // DEPLOY KEY SETUP (with confirmation)
            // ─────────────────────────────────────────────────────────────────
            $this->newLine();
            $this->line('  <fg=yellow;options=bold>╔══════════════════════════════════════════════════════════════╗</>');
            $this->line('  <fg=yellow;options=bold>║  REQUIRED: Add Deploy Key to GitHub                         ║</>');
            $this->line('  <fg=yellow;options=bold>╚══════════════════════════════════════════════════════════════╝</>');
            $this->newLine();

            // Fetch and display the public key
            $publicKey = $deployKey['public_key'] ?? null;
            if ($publicKey) {
                $this->line('  <fg=white>1.</> Go to: <fg=cyan;options=underscore>https://github.com/'.$repoInfo['full_name'].'/settings/keys</>');
                $this->line('  <fg=white>2.</> Click "<fg=green>Add deploy key</>"');
                $this->line('  <fg=white>3.</> Title: <fg=gray>'.$appName.'-deploy-key</>');
                $this->line('  <fg=white>4.</> Paste this public key:');
                $this->newLine();
                $this->line("  <fg=white;bg=gray> {$publicKey} </>");
                $this->newLine();
            } else {
                $this->line("  <fg=white>Go to:</> https://github.com/{$repoInfo['full_name']}/settings/keys");
                $this->line('  <fg=gray>Add the public key from Coolify (Security -> Private Keys)</>');
                $this->newLine();
            }

            // Wait for user to confirm they've added the deploy key
            if (! $this->option('no-interaction')) {
                pause('Press ENTER once you have added the deploy key to GitHub...');
            }

            // ─────────────────────────────────────────────────────────────────
            // WEBHOOK URL (optional, with confirmation)
            // ─────────────────────────────────────────────────────────────────
            $this->newLine();
            $this->line('  <fg=cyan;options=bold>OPTIONAL: GitHub Webhook (for automatic deploys)</>');
            $this->newLine();
            $coolifyUrl = rtrim(config('coolify.url'), '/');
            $webhookUrl = "{$coolifyUrl}/webhooks/source/github/events";
            $this->line('  <fg=white>1.</> Go to: <fg=cyan;options=underscore>https://github.com/'.$repoInfo['full_name'].'/settings/hooks</>');
            $this->line('  <fg=white>2.</> Click "<fg=green>Add webhook</>"');
            $this->line('  <fg=white>3.</> Payload URL: <fg=gray>'.$webhookUrl.'</>');
            $this->line('  <fg=white>4.</> Content type: <fg=gray>application/json</>');
            $this->line('  <fg=white>5.</> Events: <fg=gray>Just the push event</>');
            $this->newLine();

            if (! $this->option('no-interaction')) {
                pause('Press ENTER once you have set up the webhook (or to skip for now)...');
            }

            // ─────────────────────────────────────────────────────────────────
            // DEPLOY OR NEXT STEPS
            // ─────────────────────────────────────────────────────────────────
            if ($this->option('deploy')) {
                $this->newLine();
                $this->line('  <fg=yellow;options=bold>FIRST DEPLOYMENT</>');
                $this->newLine();

                $result = spin(
                    callback: fn () => $deployments->trigger($appUuid),
                    message: 'Triggering deployment...'
                );

                $deploymentUuid = $result['deployment_uuid'] ?? $result['uuid'] ?? null;

                if ($deploymentUuid) {
                    // Stream logs with debug enabled (that's where the interesting stuff is)
                    return $this->streamDeploymentLogs($deployments, $deploymentUuid, showDebug: true);
                } else {
                    $this->components->warn('Could not get deployment UUID. Check Coolify dashboard for status.');
                }
            } else {
                $this->newLine();
                $this->line('  <fg=yellow;options=bold>NEXT STEPS:</>');
                $this->newLine();
                $this->line('  <fg=white>1.</> Add the deploy key to GitHub (link above)');
                $this->line('  <fg=white>2.</> Run <fg=cyan>composer run dev</> to start your local server');
                $this->line('  <fg=white>3.</> Visit <fg=cyan>http://localhost:8000/coolify</> to copy the key and view webhook setup');
                $this->line('  <fg=white>4.</> Run <fg=cyan>php artisan coolify:deploy</> to trigger your first deployment');
                $this->newLine();
                $this->line('  <fg=gray>Or run with --deploy flag to deploy automatically</>');
            }
            $this->newLine();

        } catch (CoolifyApiException $exception) {
            $this->newLine();
            $this->components->error("Provisioning failed: {$exception->getMessage()}");

            if (! empty($this->createdResources)) {
                $this->newLine();
                warning('Some resources were created before the failure:');
                foreach ($this->createdResources as $type => $uuid) {
                    $this->line("    - {$type}: {$uuid}");
                }
                $this->newLine();
                $this->line('  <fg=gray>You may need to clean these up manually in Coolify</>');
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Display a step header.
     */
    protected function step(int $number, string $description): void
    {
        $this->newLine();
        $this->line("  <fg=cyan;options=bold>STEP {$number}:</> {$description}");
    }

    /**
     * Display a completion message for the current step.
     */
    protected function done(string $message): void
    {
        $this->line("    <fg=green>[DONE]</> {$message}");
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
                required: true,
                validate: fn (string $value) => $this->validateResourceName($value)
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
     * Create a new deploy key (SSH key) for this app.
     * Each app needs its own key because GitHub deploy keys can only be used on ONE repo.
     *
     * We generate the keypair locally and send the private key to Coolify.
     *
     * @return array{uuid: string, name: string, public_key: string}|null
     */
    protected function selectDeployKey(SecurityKeyRepository $securityKeys): ?array
    {
        $appName = $this->option('name') ?? basename(base_path());
        $keyName = "{$appName}-deploy-key";

        $this->line("    Generating new SSH keypair: {$keyName}");

        try {
            // Generate SSH keypair locally
            $keyPair = $this->generateSshKeyPair($keyName);
            if (! $keyPair) {
                return null;
            }

            // Send private key to Coolify
            $newKey = spin(
                callback: fn () => $securityKeys->create([
                    'name' => $keyName,
                    'description' => "Deploy key for {$appName} - created by coolify:provision",
                    'private_key' => $keyPair['private_key'],
                ]),
                message: 'Uploading SSH key to Coolify...'
            );

            return [
                'uuid' => $newKey['uuid'],
                'name' => $keyName,
                'public_key' => $keyPair['public_key'],
            ];
        } catch (\Exception $e) {
            $this->components->error('Failed to create SSH key: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Generate an SSH keypair locally.
     *
     * @return array{private_key: string, public_key: string}|null
     */
    protected function generateSshKeyPair(string $keyName): ?array
    {
        $tempDir = sys_get_temp_dir();
        $keyPath = "{$tempDir}/{$keyName}_".time();

        // Generate ED25519 key (more secure, shorter than RSA)
        $result = Process::run("ssh-keygen -t ed25519 -f {$keyPath} -N '' -C '{$keyName}' 2>&1");

        if (! $result->successful()) {
            $this->components->error('Failed to generate SSH key: '.$result->output());

            return null;
        }

        $privateKey = File::get($keyPath);
        $publicKey = File::get("{$keyPath}.pub");

        // Clean up temp files
        File::delete($keyPath);
        File::delete("{$keyPath}.pub");

        return [
            'private_key' => $privateKey,
            'public_key' => trim($publicKey),
        ];
    }

    /**
     * Get GitHub App if available (optional, for listing repos).
     * Returns null if not configured or rate limited.
     *
     * @return array{uuid: string, id: int}|null
     */
    protected function getOptionalGitHubApp(GitHubAppRepository $githubApps): ?array
    {
        try {
            $appList = spin(
                callback: fn () => $githubApps->all(),
                message: 'Checking for GitHub App (optional)...'
            );
        } catch (CoolifyApiException) {
            return null;
        }

        // Filter to only show non-public GitHub Apps
        $privateApps = collect($appList)->filter(fn ($app) => ! empty($app['app_id']))->values()->all();

        if (empty($privateApps)) {
            return null;
        }

        // Check for pre-configured UUID
        $preConfiguredUuid = $this->option('github-app') ?? config('coolify.github_app_uuid');
        if ($preConfiguredUuid) {
            $app = collect($privateApps)->firstWhere('uuid', $preConfiguredUuid);
            if ($app) {
                return ['uuid' => $app['uuid'], 'id' => $app['id']];
            }
        }

        // Return the first available app for repo listing
        $firstApp = $privateApps[0] ?? null;

        return $firstApp ? ['uuid' => $firstApp['uuid'], 'id' => $firstApp['id']] : null;
    }

    /**
     * Select a repository - either from GitHub App list or manual entry.
     *
     * @param  array{uuid: string, id: int}|null  $githubApp  Optional GitHub App for listing repos
     */
    protected function selectRepository(GitHubAppRepository $githubApps, ?array $githubApp): ?array
    {
        if ($repo = $this->option('repository')) {
            [$owner, $repoName] = explode('/', $repo, 2);

            return [
                'owner' => $owner,
                'repo' => $repoName,
                'full_name' => $repo,
            ];
        }

        // Get current repo from git remote to use as default
        $currentRepo = $this->getCurrentGitRepo();

        // Try to fetch repos from GitHub App if available
        if ($githubApp) {
            try {
                $response = spin(
                    callback: fn () => $githubApps->repositories($githubApp['id']),
                    message: 'Fetching repositories from GitHub App...'
                );

                $repositories = $response['repositories'] ?? $response ?? [];

                // Check for rate limit error in response
                if (isset($response['message']) && str_contains($response['message'], 'rate limit')) {
                    throw new CoolifyApiException('Rate limited');
                }

                if (! empty($repositories)) {
                    // Build a searchable list
                    $repoChoices = collect($repositories)->mapWithKeys(function ($repo) {
                        $fullName = $repo['full_name'] ?? "{$repo['owner']['login']}/{$repo['name']}";

                        return [$fullName => $fullName];
                    })->toArray();

                    // If current repo exists in the list, show it first when search is empty
                    $selected = search(
                        label: 'Search and select repository:',
                        options: function (string $value) use ($repoChoices, $currentRepo) {
                            $filtered = collect($repoChoices)
                                ->filter(fn ($name) => empty($value) || Str::contains(strtolower($name), strtolower($value)));

                            // If no search value and current repo exists, put it first
                            if (empty($value) && $currentRepo && isset($repoChoices[$currentRepo])) {
                                $filtered = $filtered->sortBy(fn ($name) => $name === $currentRepo ? 0 : 1);
                            }

                            return $filtered->toArray();
                        },
                        placeholder: $currentRepo ? "Current: {$currentRepo} (press Enter)" : 'Type to search...',
                    );

                    if ($selected) {
                        [$owner, $repoName] = explode('/', $selected, 2);

                        return [
                            'owner' => $owner,
                            'repo' => $repoName,
                            'full_name' => $selected,
                        ];
                    }
                }
            } catch (CoolifyApiException $e) {
                // Rate limited or other error - fall back to manual entry
                warning('Could not fetch repositories (GitHub may be rate limiting). Enter manually.');
            }
        }

        // Manual entry (either no GitHub App or rate limited)
        $repo = text(
            label: 'Enter repository (owner/repo)',
            placeholder: 'e.g. StuMason/my-laravel-app',
            default: $currentRepo ?? '',
            required: true,
            validate: fn (string $value) => str_contains($value, '/')
                ? null
                : 'Please enter in format: owner/repo'
        );

        [$owner, $repoName] = explode('/', $repo, 2);

        return [
            'owner' => $owner,
            'repo' => $repoName,
            'full_name' => $repo,
        ];
    }

    /**
     * Get the current repository from git remote origin.
     */
    protected function getCurrentGitRepo(): ?string
    {
        $result = Process::run('git remote get-url origin 2>/dev/null');

        if (! $result->successful() || empty(trim($result->output()))) {
            return null;
        }

        $remoteUrl = trim($result->output());

        // Only return if it's a GitHub URL
        if (! str_contains($remoteUrl, 'github.com')) {
            return null;
        }

        return $this->extractRepoName($remoteUrl);
    }

    /**
     * Select a branch - either from GitHub App list or manual entry.
     *
     * @param  array{uuid: string, id: int}|null  $githubApp  Optional GitHub App for listing branches
     */
    protected function selectBranch(GitHubAppRepository $githubApps, ?array $githubApp, string $owner, string $repo): ?string
    {
        if ($branch = $this->option('branch')) {
            return $branch;
        }

        // Try to fetch branches from GitHub App if available
        if ($githubApp) {
            try {
                $response = spin(
                    callback: fn () => $githubApps->branches($githubApp['id'], $owner, $repo),
                    message: 'Fetching branches from GitHub App...'
                );

                // Check for rate limit error
                if (isset($response['message']) && str_contains($response['message'], 'rate limit')) {
                    throw new CoolifyApiException('Rate limited');
                }

                // API returns {branches: [...]}
                $branches = $response['branches'] ?? $response ?? [];

                if (! empty($branches)) {
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
            } catch (CoolifyApiException) {
                // Rate limited or other error - fall back to manual entry
                warning('Could not fetch branches (GitHub may be rate limiting).');
            }
        }

        // Manual entry (either no GitHub App or rate limited)
        return text(
            label: 'Enter branch name',
            default: 'main',
            required: true
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
        // Quick check first - might already be ready
        $db = $databases->get($uuid);
        $status = $db['status'] ?? 'unknown';

        // Status can be compound like "running:healthy" so use str_contains
        if (str_contains($status, 'running') || str_contains($status, 'healthy')) {
            $this->storeDatabaseUrl($type, $db);
            note("{$type} is ready!");

            return;
        }

        if (str_contains($status, 'error') || str_contains($status, 'failed')) {
            throw new CoolifyApiException("{$type} failed to start");
        }

        // Not ready yet, enter the wait loop
        // Give databases up to 3 minutes to start (Docker image pull + container start)
        $maxAttempts = 60;
        $attempt = 0;

        spin(
            callback: function () use ($databases, $uuid, $type, $maxAttempts, &$attempt): bool {
                while ($attempt < $maxAttempts) {
                    $attempt++;
                    $db = $databases->get($uuid);

                    $status = $db['status'] ?? 'unknown';

                    // Status can be compound like "running:healthy" so use str_contains
                    if (str_contains($status, 'running') || str_contains($status, 'healthy')) {
                        $this->storeDatabaseUrl($type, $db);

                        return true;
                    }

                    if (str_contains($status, 'error') || str_contains($status, 'failed')) {
                        throw new CoolifyApiException("{$type} failed to start: {$status}");
                    }

                    sleep(3);
                }

                throw new CoolifyApiException("{$type} did not become ready in time (waited 3 minutes)");
            },
            message: "Waiting for {$type} to be ready..."
        );
    }

    protected function storeDatabaseUrl(string $type, array $db): void
    {
        if ($type === 'PostgreSQL') {
            $this->postgresInternalUrl = $db['internal_db_url'] ?? null;
        } else {
            $this->redisInternalUrl = $db['internal_db_url'] ?? null;
        }
    }

    /**
     * Create application using deploy key for SSH-based private repo access.
     *
     * Deploy keys don't hit GitHub API rate limits (unlike GitHub Apps).
     */
    protected function createApplication(
        ApplicationRepository $applications,
        string $serverUuid,
        string $projectUuid,
        string $environment,
        string $appName,
        string $domain,
        string $deployKeyUuid,
        string $gitRepository,
        string $branch
    ): ?string {
        $this->line("    Repository: <fg=white>{$gitRepository}:{$branch}</>");
        $this->line("    Name: <fg=white>{$appName}</>");
        $this->line("    Domain: <fg=white>https://{$domain}</>");

        // Use deploy key endpoint - creates app with SSH access (no GitHub API calls)
        $this->line('    Creating application with deploy key...');

        $payload = [
            'server_uuid' => $serverUuid,
            'project_uuid' => $projectUuid,
            'environment_name' => $environment,
            'private_key_uuid' => $deployKeyUuid,
            'git_repository' => "git@github.com:{$gitRepository}.git",
            'git_branch' => $branch,
            'ports_exposes' => '8080',
            'name' => $appName,
            'domains' => "https://{$domain}",
            'instant_deploy' => false,
            // Health check config - Laravel 11+ has /up by default
            'health_check_enabled' => true,
            'health_check_path' => '/up',
            'health_check_method' => 'GET',
            'health_check_return_code' => 200,
        ];

        try {
            $result = spin(
                callback: fn () => $applications->createPrivateDeployKey($payload),
                message: '    Calling Coolify API...'
            );

            $uuid = $result['uuid'] ?? null;

            if (! $uuid) {
                throw new CoolifyApiException('Failed to create application - no UUID returned');
            }

            $this->createdResources['Application'] = $uuid;
            $this->line("    <fg=green>Application created:</> {$uuid}");

            // Update to use Dockerfile build pack (the create endpoint defaults to nixpacks)
            $this->line('    Setting build pack to dockerfile...');
            spin(
                callback: fn () => $applications->update($uuid, ['build_pack' => 'dockerfile']),
                message: '    Updating build settings...'
            );
            $this->line('    <fg=green>Build pack set to dockerfile</>');

            return $uuid;

        } catch (\Exception $e) {
            throw new CoolifyApiException('Failed to create application: '.$e->getMessage());
        }
    }

    protected function setApplicationEnvVars(
        ApplicationRepository $applications,
        string $appUuid,
        string $projectUuid,
        ?string $dbUuid,
        ?string $redisUuid,
        DatabaseRepository $databases,
        string $serverUuid,
        string $environment,
        string $deployKeyUuid,
        string $repository,
        string $branch,
        string $appName,
        string $domain
    ): void {
        $envVars = [];

        // Core Laravel app config
        $envVars[] = ['key' => 'APP_NAME', 'value' => $appName];
        $envVars[] = ['key' => 'APP_ENV', 'value' => 'production'];
        $envVars[] = ['key' => 'APP_KEY', 'value' => 'base64:'.base64_encode(random_bytes(32))];
        $envVars[] = ['key' => 'APP_URL', 'value' => "https://{$domain}"];
        $envVars[] = ['key' => 'ASSET_URL', 'value' => "https://{$domain}"];

        // Log management best practices (prevents runaway log files)
        $envVars[] = ['key' => 'LOG_STACK', 'value' => 'daily'];
        $envVars[] = ['key' => 'LOG_DAILY_MAX_FILES', 'value' => '7'];

        // Set Coolify connection (so deployed app can use this package's dashboard/API)
        $envVars[] = ['key' => 'COOLIFY_URL', 'value' => config('coolify.url')];
        $envVars[] = ['key' => 'COOLIFY_TOKEN', 'value' => config('coolify.token')];

        // Set Coolify resource UUIDs so they're available in production
        $envVars[] = ['key' => 'COOLIFY_SERVER_UUID', 'value' => $serverUuid];
        $envVars[] = ['key' => 'COOLIFY_PROJECT_UUID', 'value' => $projectUuid];
        $envVars[] = ['key' => 'COOLIFY_ENVIRONMENT', 'value' => $environment];
        $envVars[] = ['key' => 'COOLIFY_DEPLOY_KEY_UUID', 'value' => $deployKeyUuid];
        $envVars[] = ['key' => 'COOLIFY_REPOSITORY', 'value' => $repository];
        $envVars[] = ['key' => 'COOLIFY_BRANCH', 'value' => $branch];
        $envVars[] = ['key' => 'COOLIFY_APPLICATION_UUID', 'value' => $appUuid];

        if ($dbUuid) {
            $envVars[] = ['key' => 'COOLIFY_DATABASE_UUID', 'value' => $dbUuid];
        }

        if ($redisUuid) {
            $envVars[] = ['key' => 'COOLIFY_REDIS_UUID', 'value' => $redisUuid];
        }

        // Fetch PostgreSQL connection details
        if ($dbUuid) {
            $db = $databases->get($dbUuid);
            $internalUrl = $db['internal_db_url'] ?? $this->postgresInternalUrl;

            if ($internalUrl) {
                $envVars[] = ['key' => 'DATABASE_URL', 'value' => $internalUrl];
                $envVars[] = ['key' => 'DB_CONNECTION', 'value' => 'pgsql'];

                // Parse the URL to extract individual components
                $parsed = parse_url($internalUrl);
                if ($parsed) {
                    $envVars[] = ['key' => 'DB_HOST', 'value' => $parsed['host'] ?? ''];
                    $envVars[] = ['key' => 'DB_PORT', 'value' => (string) ($parsed['port'] ?? 5432)];
                    $envVars[] = ['key' => 'DB_DATABASE', 'value' => ltrim($parsed['path'] ?? '', '/')];
                    $envVars[] = ['key' => 'DB_USERNAME', 'value' => $parsed['user'] ?? ''];
                    $envVars[] = ['key' => 'DB_PASSWORD', 'value' => $parsed['pass'] ?? ''];
                }
            }
        }

        // Fetch Redis/Dragonfly connection details
        if ($redisUuid) {
            $redis = $databases->get($redisUuid);
            $internalUrl = $redis['internal_db_url'] ?? $this->redisInternalUrl;

            if ($internalUrl) {
                $envVars[] = ['key' => 'REDIS_URL', 'value' => $internalUrl];
                $envVars[] = ['key' => 'CACHE_STORE', 'value' => 'redis'];
                $envVars[] = ['key' => 'SESSION_DRIVER', 'value' => 'redis'];
                $envVars[] = ['key' => 'QUEUE_CONNECTION', 'value' => 'redis'];

                // Parse the URL for individual components
                $parsed = parse_url($internalUrl);
                if ($parsed) {
                    $envVars[] = ['key' => 'REDIS_HOST', 'value' => $parsed['host'] ?? ''];
                    $envVars[] = ['key' => 'REDIS_PORT', 'value' => (string) ($parsed['port'] ?? 6379)];
                    if (! empty($parsed['pass'])) {
                        $envVars[] = ['key' => 'REDIS_PASSWORD', 'value' => $parsed['pass']];
                    }
                }
            }
        }

        // Copy REVERB_* vars from local .env (only if app uses Reverb)
        $reverbVars = $this->getLocalEnvVars(['REVERB_']);

        if (! empty($reverbVars)) {
            // Generate missing credentials if needed
            if (! isset($reverbVars['REVERB_APP_ID'])) {
                $reverbVars['REVERB_APP_ID'] = (string) random_int(100000, 999999);
            }
            if (! isset($reverbVars['REVERB_APP_KEY'])) {
                $reverbVars['REVERB_APP_KEY'] = Str::random(20);
            }
            if (! isset($reverbVars['REVERB_APP_SECRET'])) {
                $reverbVars['REVERB_APP_SECRET'] = Str::random(20);
            }

            // Set broadcasting config for Laravel Reverb
            $envVars[] = ['key' => 'BROADCAST_CONNECTION', 'value' => 'reverb'];
            $envVars[] = ['key' => 'REVERB_APP_ID', 'value' => $reverbVars['REVERB_APP_ID']];
            $envVars[] = ['key' => 'REVERB_APP_KEY', 'value' => $reverbVars['REVERB_APP_KEY']];
            $envVars[] = ['key' => 'REVERB_APP_SECRET', 'value' => $reverbVars['REVERB_APP_SECRET']];
            $envVars[] = ['key' => 'REVERB_HOST', 'value' => $domain];
            $envVars[] = ['key' => 'REVERB_PORT', 'value' => '443'];
            $envVars[] = ['key' => 'REVERB_SCHEME', 'value' => 'https'];

            // Copy other REVERB_* vars from local .env
            foreach ($reverbVars as $key => $value) {
                if (in_array($key, ['REVERB_APP_ID', 'REVERB_APP_KEY', 'REVERB_APP_SECRET'])) {
                    continue;
                }
                $envVars[] = ['key' => $key, 'value' => $value];
            }

            // Set VITE_REVERB_* with production values (browser needs to connect to prod domain)
            $envVars[] = ['key' => 'VITE_REVERB_APP_KEY', 'value' => $reverbVars['REVERB_APP_KEY']];
            $envVars[] = ['key' => 'VITE_REVERB_HOST', 'value' => $domain];
            $envVars[] = ['key' => 'VITE_REVERB_PORT', 'value' => '443'];
            $envVars[] = ['key' => 'VITE_REVERB_SCHEME', 'value' => 'https'];

            $this->line('    Configured Reverb for production');
        }

        // Copy VITE_* vars from local .env (with variable interpolation)
        $viteVars = $this->getLocalEnvVars(['VITE_']);
        foreach ($viteVars as $key => $value) {
            // Skip VITE_REVERB_* if we already set them above
            if (! empty($reverbVars) && Str::startsWith($key, 'VITE_REVERB_')) {
                continue;
            }
            $envVars[] = ['key' => $key, 'value' => $value];
        }

        if (count($viteVars) > 0) {
            $this->line('    Copying <fg=white>'.count($viteVars).'</> VITE vars from local .env');
        }

        $this->line('    Setting <fg=white>'.count($envVars).'</> environment variables...');

        spin(
            callback: function () use ($applications, $appUuid, $envVars): void {
                foreach ($envVars as $env) {
                    $applications->createEnv($appUuid, $env);
                }
            },
            message: '    Pushing to Coolify...'
        );

        $this->line('    <fg=green>Environment variables configured on Coolify</>');
    }

    /**
     * Get environment variables from local .env file matching given prefixes.
     * Resolves variable interpolation like ${APP_NAME} to actual values.
     *
     * @param  array<string>  $prefixes  Array of prefixes to match (e.g., ['REVERB_', 'VITE_'])
     * @return array<string, string> Key-value pairs of matching env vars with resolved values
     */
    protected function getLocalEnvVars(array $prefixes): array
    {
        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            return [];
        }

        $content = File::get($envPath);
        $lines = explode("\n", $content);

        // First pass: collect all env vars for interpolation
        $allVars = [];
        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments and empty lines
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            // Parse KEY=value
            if (preg_match('/^([A-Z_][A-Z0-9_]*)=(.*)$/i', $line, $matches)) {
                $key = $matches[1];
                $value = $matches[2];

                // Remove quotes if present
                $value = trim($value, '"\'');

                $allVars[$key] = $value;
            }
        }

        // Second pass: resolve interpolation and filter by prefix
        $result = [];
        foreach ($allVars as $key => $value) {
            // Check if key matches any of the prefixes
            $matches = false;
            foreach ($prefixes as $prefix) {
                if (str_starts_with($key, $prefix)) {
                    $matches = true;
                    break;
                }
            }

            if (! $matches) {
                continue;
            }

            // Resolve ${VAR} and $VAR references
            $resolved = preg_replace_callback(
                '/\$\{([A-Z_][A-Z0-9_]*)\}|\$([A-Z_][A-Z0-9_]*)/i',
                function ($m) use ($allVars) {
                    $varName = $m[1] ?: $m[2];

                    return $allVars[$varName] ?? '';
                },
                $value
            );

            $result[$key] = $resolved;
        }

        return $result;
    }

    /**
     * Validate a resource name (project, application, database).
     */
    protected function validateResourceName(string $value): ?string
    {
        // Must be at least 2 characters
        if (strlen($value) < 2) {
            return 'Name must be at least 2 characters.';
        }

        // Must be no more than 255 characters
        if (strlen($value) > 255) {
            return 'Name must be no more than 255 characters.';
        }

        // Only allow alphanumeric, spaces, hyphens, underscores
        if (! preg_match('/^[a-zA-Z0-9\s\-_]+$/', $value)) {
            return 'Name can only contain letters, numbers, spaces, hyphens, and underscores.';
        }

        return null;
    }

    /**
     * Validate a domain name.
     */
    protected function validateDomain(string $value): ?string
    {
        // Remove protocol if provided
        $domain = preg_replace('#^https?://#', '', $value);

        // Basic domain validation
        if (! preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\-\.]+[a-zA-Z0-9]$/', $domain)) {
            return 'Please enter a valid domain (e.g., myapp.example.com)';
        }

        // Must have at least one dot
        if (! str_contains($domain, '.')) {
            return 'Domain must include a TLD (e.g., myapp.example.com)';
        }

        return null;
    }

    /**
     * Run pre-flight checks before provisioning.
     */
    protected function runPreflightChecks(): bool
    {
        $this->line('  <fg=cyan;options=bold>PRE-FLIGHT CHECKS</>');
        $this->newLine();

        $allPassed = true;

        // Check 1: Git repository exists
        $gitCheck = $this->checkGitRepository();
        if ($gitCheck === false) {
            $allPassed = false;
        }

        // Check 2: Dockerfile exists
        $dockerfileCheck = $this->checkDockerfile();
        if ($dockerfileCheck === false) {
            $allPassed = false;
        }

        $this->newLine();

        return $allPassed;
    }

    /**
     * Check if a git repository exists and has a remote.
     */
    protected function checkGitRepository(): bool
    {
        // Check if .git directory exists
        if (! File::isDirectory(base_path('.git'))) {
            $this->line('    <fg=red>[✗]</> Git repository not found');
            $this->newLine();
            $this->line('        <fg=yellow>This project needs to be a git repository on GitHub.</>');
            $this->newLine();
            $this->line('        <fg=white>To create one:</>');
            $this->line('        <fg=gray>1.</> git init');
            $this->line('        <fg=gray>2.</> git add .');
            $this->line('        <fg=gray>3.</> git commit -m "Initial commit"');
            $this->line('        <fg=gray>4.</> gh repo create --private --source=. --push');
            $this->newLine();

            return false;
        }

        // Check if remote origin exists
        $result = Process::run('git remote get-url origin 2>/dev/null');

        if (! $result->successful() || empty(trim($result->output()))) {
            $this->line('    <fg=red>[✗]</> No GitHub remote found');
            $this->newLine();
            $this->line('        <fg=yellow>This project needs a GitHub remote to deploy.</>');
            $this->newLine();
            $this->line('        <fg=white>To create one:</>');
            $this->line('        <fg=gray>gh repo create --private --source=. --push</>');
            $this->newLine();

            return false;
        }

        $remoteUrl = trim($result->output());

        // Check if it's a GitHub URL
        if (! str_contains($remoteUrl, 'github.com')) {
            $this->line('    <fg=yellow>[!]</> Remote is not GitHub: '.$remoteUrl);
            $this->newLine();
            $this->line('        <fg=gray>Coolify works best with GitHub repositories.</>');
            $this->newLine();

            // Don't fail, just warn - might work with other git hosts
            return true;
        }

        $this->line('    <fg=green>[✓]</> Git repository: '.$this->extractRepoName($remoteUrl));

        return true;
    }

    /**
     * Extract repository name from git URL.
     */
    protected function extractRepoName(string $url): string
    {
        // Handle SSH format: git@github.com:owner/repo.git
        if (preg_match('/github\.com[:\\/]([^\\/]+\\/[^\\/]+?)(?:\.git)?$/', $url, $matches)) {
            return $matches[1];
        }

        return $url;
    }

    /**
     * Check if Dockerfile exists.
     */
    protected function checkDockerfile(): bool
    {
        $dockerfilePath = base_path('Dockerfile');

        if (! File::exists($dockerfilePath)) {
            $this->line('    <fg=yellow>[!]</> Dockerfile not found');
            $this->newLine();
            $this->line('        <fg=gray>Run:</> php artisan coolify:install');
            $this->line('        <fg=gray>This will generate an optimized Dockerfile for your Laravel app.</>');
            $this->newLine();

            if (! $this->option('force') && ! $this->option('no-interaction')) {
                if (confirm('Would you like to generate Dockerfile now?', true)) {
                    $exitCode = $this->call('coolify:install', ['--force' => true]);
                    $this->newLine();

                    if ($exitCode === self::SUCCESS && File::exists($dockerfilePath)) {
                        $this->line('    <fg=green>[✓]</> Dockerfile generated');

                        return true;
                    }

                    if ($exitCode !== self::SUCCESS) {
                        $this->line('    <fg=red>[✗]</> Failed to generate Dockerfile');

                        return true; // Don't fail provisioning, just warn
                    }
                }
            }

            // Warn but don't fail - Coolify can build without Dockerfile
            return true;
        }

        $this->line('    <fg=green>[✓]</> Dockerfile found');

        return true;
    }
}
