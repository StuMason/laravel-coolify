<?php

namespace Stumason\Coolify\Console;

use Illuminate\Console\Command;
use Stumason\Coolify\Contracts\DeploymentRepository;
use Stumason\Coolify\CoolifyClient;
use Stumason\Coolify\Exceptions\CoolifyApiException;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

#[AsCommand(name: 'coolify:rollback')]
class RollbackCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coolify:rollback
                            {--uuid= : Application UUID (defaults to config)}
                            {--deployment= : Specific deployment UUID to rollback to}
                            {--force : Rollback without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rollback to a previous deployment on Coolify';

    /**
     * Execute the console command.
     */
    public function handle(CoolifyClient $client, DeploymentRepository $deployments): int
    {
        if (! $client->isConfigured()) {
            $this->components->error('Coolify is not configured. Please set COOLIFY_URL and COOLIFY_TOKEN in your .env file.');

            return self::FAILURE;
        }

        $applicationUuid = $this->option('uuid') ?? config('coolify.application_uuid');

        if (! $applicationUuid) {
            $this->components->error('No application UUID configured. Set COOLIFY_APPLICATION_UUID in your .env file or use --uuid option.');

            return self::FAILURE;
        }

        try {
            // Get recent deployments
            $recentDeployments = $deployments->forApplication($applicationUuid);

            if (count($recentDeployments) < 2) {
                $this->components->error('Not enough deployment history to rollback.');

                return self::FAILURE;
            }

            // Determine which deployment to rollback to
            $deploymentUuid = $this->option('deployment');

            if (! $deploymentUuid && ! $this->option('no-interaction')) {
                // Let user select from recent deployments
                $choices = collect($recentDeployments)
                    ->take(10)
                    ->mapWithKeys(fn ($d) => [
                        $d['uuid'] => sprintf(
                            '%s - %s (%s)',
                            $d['uuid'] ? substr($d['uuid'], 0, 8) : 'N/A',
                            $d['git_commit_sha'] ? substr($d['git_commit_sha'], 0, 7) : 'N/A',
                            $d['status'] ?? 'unknown'
                        ),
                    ])
                    ->toArray();

                // Skip the first one (current deployment)
                array_shift($choices);

                if (empty($choices)) {
                    $this->components->error('No previous deployments available for rollback.');

                    return self::FAILURE;
                }

                $deploymentUuid = select(
                    label: 'Select deployment to rollback to:',
                    options: $choices,
                );
            }

            if (! $deploymentUuid) {
                // Default to the second most recent (previous) deployment
                $deploymentUuid = $recentDeployments[1]['uuid'] ?? null;

                if (! $deploymentUuid) {
                    $this->components->error('Could not determine deployment to rollback to.');

                    return self::FAILURE;
                }
            }

            // Confirm rollback
            if (! $this->option('force') && ! $this->option('no-interaction')) {
                if (! confirm("Rollback to deployment {$deploymentUuid}?", default: false)) {
                    $this->components->warn('Rollback cancelled.');

                    return self::SUCCESS;
                }
            }

            // Trigger rollback
            spin(
                callback: fn () => $deployments->rollback($applicationUuid, $deploymentUuid),
                message: 'Rolling back...'
            );

            $this->components->info('Rollback triggered successfully!');

        } catch (CoolifyApiException $e) {
            $this->components->error("Rollback failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
