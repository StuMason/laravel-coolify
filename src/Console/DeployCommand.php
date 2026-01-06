<?php

namespace Stumason\Coolify\Console;

use Illuminate\Console\Command;
use Stumason\Coolify\Console\Concerns\StreamsDeploymentLogs;
use Stumason\Coolify\Contracts\ApplicationRepository;
use Stumason\Coolify\Contracts\DeploymentRepository;
use Stumason\Coolify\CoolifyClient;
use Stumason\Coolify\Exceptions\CoolifyApiException;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\spin;

#[AsCommand(name: 'coolify:deploy')]
class DeployCommand extends Command
{
    use StreamsDeploymentLogs;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coolify:deploy
                            {--uuid= : Application UUID (defaults to config)}
                            {--tag= : Deploy a specific git tag}
                            {--force : Force deployment without confirmation}
                            {--wait : Wait for deployment to complete and stream logs}
                            {--debug : Show debug/build logs (enabled by default with --wait)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deploy your application on Coolify';

    /**
     * Execute the console command.
     */
    public function handle(
        CoolifyClient $client,
        ApplicationRepository $applications,
        DeploymentRepository $deployments
    ): int {
        if (! $client->isConfigured()) {
            $this->components->error('Coolify is not configured. Please set COOLIFY_URL and COOLIFY_TOKEN in your .env file.');

            return self::FAILURE;
        }

        $uuid = $this->option('uuid') ?? config('coolify.application_uuid');

        if (! $uuid) {
            $this->components->error('No application UUID configured. Set COOLIFY_APPLICATION_UUID in your .env file or use --uuid option.');

            return self::FAILURE;
        }

        // Confirm deployment
        if (! $this->option('force') && ! $this->option('no-interaction')) {
            try {
                $app = $applications->get($uuid);
                $appName = $app['name'] ?? $uuid;

                if (! confirm("Deploy {$appName}?", default: true)) {
                    $this->components->warn('Deployment cancelled.');

                    return self::SUCCESS;
                }
            } catch (CoolifyApiException $e) {
                $this->components->error("Failed to fetch application: {$e->getMessage()}");

                return self::FAILURE;
            }
        }

        // Trigger deployment
        try {
            $tag = $this->option('tag');

            $result = spin(
                callback: function () use ($deployments, $uuid, $tag) {
                    if ($tag) {
                        return $deployments->deployTag($uuid, $tag);
                    }

                    return $deployments->trigger($uuid);
                },
                message: $tag ? "Deploying tag {$tag}..." : 'Triggering deployment...'
            );

            $deploymentUuid = $result['deployment_uuid'] ?? $result['uuid'] ?? null;

            if ($deploymentUuid) {
                $this->components->info('Deployment triggered successfully!');
                $this->components->twoColumnDetail('Deployment UUID', $deploymentUuid);

                if ($this->option('wait')) {
                    // Debug is enabled by default with --wait (that's where the interesting stuff is)
                    $showDebug = $this->option('debug') !== false;

                    return $this->streamDeploymentLogs($deployments, $deploymentUuid, $showDebug);
                }

                $this->newLine();
                $this->line("  Run <comment>php artisan coolify:logs --deployment={$deploymentUuid}</comment> to view deployment logs.");
            } else {
                $this->components->info('Deployment triggered successfully!');
            }

        } catch (CoolifyApiException $e) {
            $this->components->error("Deployment failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
