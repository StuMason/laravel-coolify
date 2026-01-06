<?php

namespace Stumason\Coolify\Console;

use Illuminate\Console\Command;
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
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coolify:deploy
                            {--uuid= : Application UUID (defaults to config)}
                            {--tag= : Deploy a specific git tag}
                            {--force : Force deployment without confirmation}
                            {--wait : Wait for deployment to complete}';

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
                    return $this->waitForDeployment($deployments, $deploymentUuid);
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

    /**
     * Wait for a deployment to complete.
     */
    protected function waitForDeployment(DeploymentRepository $deployments, string $uuid): int
    {
        $this->newLine();
        $this->components->info('Waiting for deployment to complete...');

        $maxAttempts = 120; // 10 minutes with 5 second intervals
        $attempts = 0;

        while ($attempts < $maxAttempts) {
            try {
                $deployment = $deployments->get($uuid);
                $status = strtolower($deployment['status'] ?? 'unknown');

                if ($status === 'finished' || $status === 'success') {
                    $this->newLine();
                    $this->components->info('Deployment completed successfully!');

                    return self::SUCCESS;
                }

                if ($status === 'failed' || $status === 'error') {
                    $this->newLine();
                    $this->components->error('Deployment failed.');

                    return self::FAILURE;
                }

                if ($status === 'cancelled') {
                    $this->newLine();
                    $this->components->warn('Deployment was cancelled.');

                    return self::FAILURE;
                }

                // Still in progress
                $this->output->write('.');

            } catch (CoolifyApiException $e) {
                $this->components->error("Failed to check deployment status: {$e->getMessage()}");

                return self::FAILURE;
            }

            sleep(5);
            $attempts++;
        }

        $this->newLine();
        $this->components->warn('Timed out waiting for deployment. It may still be in progress.');

        return self::SUCCESS;
    }
}
