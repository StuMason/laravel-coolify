<?php

namespace Stumason\Coolify\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Stumason\Coolify\Contracts\ApplicationRepository;
use Stumason\Coolify\Contracts\DatabaseRepository;
use Stumason\Coolify\Contracts\DeploymentRepository;
use Stumason\Coolify\Contracts\SecurityKeyRepository;
use Stumason\Coolify\CoolifyClient;
use Stumason\Coolify\Exceptions\CoolifyApiException;
use Stumason\Coolify\Models\CoolifyResource;

class DashboardStatsController extends Controller
{
    /**
     * Get the key performance stats for the dashboard.
     */
    public function index(
        CoolifyClient $client,
        ApplicationRepository $applications,
        DatabaseRepository $databases,
        DeploymentRepository $deployments,
        SecurityKeyRepository $securityKeys
    ): JsonResponse {
        $stats = [
            'connected' => false,
            'application' => null,
            'databases' => [],
            'recentDeployments' => [],
            'deployKey' => null,
        ];

        try {
            // Test connection
            $stats['connected'] = $client->testConnection();

            if (! $stats['connected']) {
                return response()->json($stats);
            }

            // Get resource configuration from database
            $resource = CoolifyResource::getDefault();

            // Get deploy key info if configured
            if ($deployKeyUuid = $resource?->deploy_key_uuid) {
                try {
                    $key = $securityKeys->get($deployKeyUuid);
                    $stats['deployKey'] = [
                        'uuid' => $key['uuid'] ?? null,
                        'name' => $key['name'] ?? 'Unknown',
                        'public_key' => $key['public_key'] ?? null,
                    ];
                } catch (CoolifyApiException) {
                    // Key not found
                }
            }

            // Get application status
            if ($uuid = $resource?->application_uuid) {
                try {
                    $app = $applications->get($uuid);

                    // Determine if using deploy key (no GitHub App)
                    $usesDeployKey = empty($app['source_id']) || $app['source_type'] === null;
                    $webhookSecret = $app['manual_webhook_secret_github'] ?? null;

                    // Build webhook URL if using deploy key
                    $webhookUrl = null;
                    if ($usesDeployKey && $webhookSecret) {
                        $coolifyUrl = rtrim(config('coolify.url'), '/');
                        $webhookUrl = "{$coolifyUrl}/webhooks/source/github/events/manual?source={$uuid}&webhook_secret={$webhookSecret}";
                    }

                    // Build proper Coolify dashboard URL
                    // Format: /project/{project_uuid}/environment/{environment_uuid}/application/{app_uuid}
                    $coolifyUrl = rtrim(config('coolify.url'), '/');
                    $projectUuid = $app['project']['uuid'] ?? $resource->project_uuid;
                    $environmentUuid = $app['environment']['uuid'] ?? null;
                    $coolifyDashboardUrl = null;
                    if ($projectUuid && $environmentUuid) {
                        $coolifyDashboardUrl = "{$coolifyUrl}/project/{$projectUuid}/environment/{$environmentUuid}/application/{$uuid}";
                    }

                    $stats['application'] = [
                        'uuid' => $app['uuid'] ?? null,
                        'name' => $app['name'] ?? 'Unknown',
                        'description' => $app['description'] ?? null,
                        'status' => $app['status'] ?? 'unknown',
                        'fqdn' => $app['fqdn'] ?? null,
                        'repository' => $app['git_repository'] ?? null,
                        'branch' => $app['git_branch'] ?? null,
                        'commit' => isset($app['git_commit_sha']) ? substr($app['git_commit_sha'], 0, 7) : null,
                        'full_commit' => $app['git_commit_sha'] ?? null,
                        'build_pack' => $app['build_pack'] ?? null,
                        'created_at' => $app['created_at'] ?? null,
                        'updated_at' => $app['updated_at'] ?? null,
                        'uses_deploy_key' => $usesDeployKey,
                        'webhook_secret' => $webhookSecret,
                        'webhook_url' => $webhookUrl,
                        'webhook_configured' => $usesDeployKey ? ! empty($webhookSecret) : true,
                        'coolify_url' => $coolifyDashboardUrl,
                        'project_uuid' => $projectUuid,
                        'environment_uuid' => $environmentUuid,
                        'environment_name' => $app['environment']['name'] ?? null,
                        'project_name' => $app['project']['name'] ?? null,
                    ];

                    // Get recent deployments
                    try {
                        $recentDeployments = $deployments->forApplication($uuid);
                        $stats['recentDeployments'] = collect($recentDeployments)
                            ->take(10)
                            ->map(fn ($d) => [
                                'uuid' => $d['deployment_uuid'] ?? $d['uuid'] ?? null,
                                'status' => $d['status'] ?? 'unknown',
                                'commit' => isset($d['commit']) ? substr($d['commit'], 0, 7) : null,
                                'full_commit' => $d['commit'] ?? null,
                                'commit_message' => $d['commit_message'] ?? null,
                                'created_at' => $d['created_at'] ?? null,
                                'finished_at' => $d['finished_at'] ?? null,
                            ])
                            ->values()
                            ->toArray();
                    } catch (CoolifyApiException) {
                        // Ignore deployment fetch errors
                    }
                } catch (CoolifyApiException) {
                    // Application not found or error
                }
            }

            // Get database status
            if ($dbUuid = $resource?->database_uuid) {
                try {
                    $db = $databases->get($dbUuid);
                    $stats['databases']['primary'] = $this->formatDatabaseInfo($db, 'database');
                } catch (CoolifyApiException) {
                    // Database not found or error
                }
            }

            // Get redis status
            if ($redisUuid = $resource?->redis_uuid) {
                try {
                    $redis = $databases->get($redisUuid);
                    $stats['databases']['redis'] = $this->formatDatabaseInfo($redis, 'redis');
                } catch (CoolifyApiException) {
                    // Redis not found or error
                }
            }

        } catch (CoolifyApiException) {
            $stats['connected'] = false;
        }

        return response()->json($stats);
    }

    /**
     * Format database info for the dashboard.
     */
    protected function formatDatabaseInfo(array $db, string $category): array
    {
        // Determine type from database_type field
        $dbType = $db['database_type'] ?? 'unknown';
        $displayType = match (true) {
            str_contains($dbType, 'postgresql') => 'PostgreSQL',
            str_contains($dbType, 'mysql') => 'MySQL',
            str_contains($dbType, 'mariadb') => 'MariaDB',
            str_contains($dbType, 'redis') => 'Redis',
            str_contains($dbType, 'dragonfly') => 'Dragonfly',
            str_contains($dbType, 'mongodb') => 'MongoDB',
            str_contains($dbType, 'keydb') => 'KeyDB',
            default => $dbType,
        };

        return [
            'uuid' => $db['uuid'] ?? null,
            'name' => $db['name'] ?? 'Unknown',
            'type' => $displayType,
            'database_type' => $dbType,
            'status' => $db['status'] ?? 'unknown',
            'image' => $db['image'] ?? null,
            'category' => $category,
            // Connection info (redact passwords)
            'internal_db_url' => $db['internal_db_url'] ?? null,
            'external_db_url' => $db['external_db_url'] ?? null,
            'is_public' => $db['is_public'] ?? false,
            'public_port' => $db['public_port'] ?? null,
            // Resource limits
            'limits_memory' => $db['limits_memory'] ?? '0',
            'limits_cpus' => $db['limits_cpus'] ?? '0',
            // Timestamps
            'started_at' => $db['started_at'] ?? null,
            'last_online_at' => $db['last_online_at'] ?? null,
            'created_at' => $db['created_at'] ?? null,
            // Specific fields for postgres
            'postgres_db' => $db['postgres_db'] ?? null,
            'postgres_user' => $db['postgres_user'] ?? null,
            // Specific fields for redis/dragonfly
            'dragonfly_password' => isset($db['dragonfly_password']) ? '••••••••' : null,
            'redis_password' => isset($db['redis_password']) ? '••••••••' : null,
        ];
    }
}
