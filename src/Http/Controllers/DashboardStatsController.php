<?php

namespace Stumason\Coolify\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Stumason\Coolify\Contracts\ApplicationRepository;
use Stumason\Coolify\Contracts\DatabaseRepository;
use Stumason\Coolify\Contracts\DeploymentRepository;
use Stumason\Coolify\Contracts\ProjectRepository;
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
        SecurityKeyRepository $securityKeys,
        ProjectRepository $projects
    ): JsonResponse {
        $stats = [
            'connected' => false,
            'application' => null,
            'databases' => [],
            'recentDeployments' => [],
            'deployKey' => null,
            'project' => null,
            'environment' => null,
            'server' => null,
            'coolify_url' => rtrim(config('coolify.url'), '/'),
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

            // Fetch project and environment info from resource config
            $projectUuid = $resource?->project_uuid;
            $environmentName = $resource?->environment;
            $environmentUuid = null;

            if ($projectUuid) {
                try {
                    $project = $projects->get($projectUuid);
                    $stats['project'] = $project;

                    // Find environment UUID by name
                    if ($environmentName && ! empty($project['environments'])) {
                        foreach ($project['environments'] as $env) {
                            if (($env['name'] ?? null) === $environmentName) {
                                $environmentUuid = $env['uuid'] ?? null;
                                $stats['environment'] = $env;
                                break;
                            }
                        }
                    }
                } catch (CoolifyApiException) {
                    // Project not found
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
                    $coolifyUrl = rtrim(config('coolify.url'), '/');
                    if ($usesDeployKey && $webhookSecret) {
                        $webhookUrl = "{$coolifyUrl}/webhooks/source/github/events/manual?source={$uuid}&webhook_secret={$webhookSecret}";
                    }

                    // Build proper Coolify dashboard URL
                    // Format: /project/{project_uuid}/environment/{environment_uuid}/application/{app_uuid}
                    $coolifyDashboardUrl = null;
                    if ($projectUuid && $environmentUuid) {
                        $coolifyDashboardUrl = "{$coolifyUrl}/project/{$projectUuid}/environment/{$environmentUuid}/application/{$uuid}";
                    }

                    // Return ALL application data from Coolify, plus computed fields
                    $stats['application'] = array_merge($app, [
                        // Computed convenience fields
                        'repository' => $app['git_repository'] ?? null,
                        'branch' => $app['git_branch'] ?? null,
                        'commit' => isset($app['git_commit_sha']) ? substr($app['git_commit_sha'], 0, 7) : null,
                        'full_commit' => $app['git_commit_sha'] ?? null,
                        'uses_deploy_key' => $usesDeployKey,
                        'webhook_secret' => $webhookSecret,
                        'webhook_url' => $webhookUrl,
                        'webhook_configured' => $usesDeployKey ? ! empty($webhookSecret) : true,
                        'coolify_url' => $coolifyDashboardUrl,
                        'project_uuid' => $projectUuid,
                        'environment_uuid' => $environmentUuid,
                        'environment_name' => $environmentName,
                        'project_name' => $stats['project']['name'] ?? null,
                    ]);

                    // Add server info from app destination
                    if (isset($app['destination']['server'])) {
                        $stats['server'] = $app['destination']['server'];
                    }

                    // Get recent deployments
                    try {
                        $recentDeployments = $deployments->forApplication($uuid);
                        $stats['recentDeployments'] = collect($recentDeployments)
                            ->take(10)
                            ->map(function ($d) {
                                // Calculate duration if both timestamps exist
                                $duration = null;
                                if (! empty($d['created_at']) && ! empty($d['finished_at'])) {
                                    $start = strtotime($d['created_at']);
                                    $end = strtotime($d['finished_at']);
                                    if ($start && $end) {
                                        $duration = $end - $start;
                                    }
                                }

                                return [
                                    'uuid' => $d['deployment_uuid'] ?? $d['uuid'] ?? null,
                                    'status' => $d['status'] ?? 'unknown',
                                    'commit' => $d['commit'] ?? null,
                                    'commit_message' => $d['commit_message'] ?? null,
                                    'created_at' => $d['created_at'] ?? null,
                                    'finished_at' => $d['finished_at'] ?? null,
                                    'duration' => $duration,
                                ];
                            })
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
        // Determine display type from database_type field
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

        // Return ALL database data from Coolify, plus computed fields
        return array_merge($db, [
            'type' => $displayType,
            'category' => $category,
        ]);
    }
}
