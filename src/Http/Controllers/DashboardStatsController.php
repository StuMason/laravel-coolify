<?php

namespace Stumason\Coolify\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stumason\Coolify\Contracts\ApplicationRepository;
use Stumason\Coolify\Contracts\DatabaseRepository;
use Stumason\Coolify\Contracts\DeploymentRepository;
use Stumason\Coolify\Contracts\ProjectRepository;
use Stumason\Coolify\CoolifyClient;
use Stumason\Coolify\Exceptions\CoolifyApiException;

class DashboardStatsController extends Controller
{
    /**
     * Get the key performance stats for the dashboard.
     */
    public function index(
        Request $request,
        CoolifyClient $client,
        ApplicationRepository $applications,
        DatabaseRepository $databases,
        DeploymentRepository $deployments,
        ProjectRepository $projects
    ): JsonResponse {
        $stats = [
            'connected' => false,
            'application' => null,
            'databases' => [],
            'recentDeployments' => [],
            'project' => null,
            'environment' => null,
            'environments' => [],
            'server' => null,
            'coolify_url' => rtrim(config('coolify.url'), '/'),
        ];

        try {
            // Test connection
            $stats['connected'] = $client->testConnection();

            if (! $stats['connected']) {
                return response()->json($stats);
            }

            // Get project from config
            $projectUuid = config('coolify.project_uuid');
            $environmentName = $request->query('environment', 'production');
            $environmentUuid = null;

            if ($projectUuid) {
                try {
                    $project = $projects->get($projectUuid);
                    $stats['project'] = $project;
                    $stats['environments'] = $project['environments'] ?? [];

                    // Find environment by name
                    if (! empty($project['environments'])) {
                        foreach ($project['environments'] as $env) {
                            if (($env['name'] ?? null) === $environmentName) {
                                $environmentUuid = $env['uuid'] ?? null;
                                $stats['environment'] = $env;
                                break;
                            }
                        }

                        // If environment not found, use first available
                        if (! $stats['environment'] && count($project['environments']) > 0) {
                            $stats['environment'] = $project['environments'][0];
                            $environmentName = $stats['environment']['name'] ?? 'production';
                            $environmentUuid = $stats['environment']['uuid'] ?? null;
                        }
                    }
                } catch (CoolifyApiException) {
                    // Project not found
                }
            }

            // Fetch environment resources (applications, databases, services)
            $envResources = null;
            if ($projectUuid && $environmentName) {
                try {
                    $envResources = $projects->environment($projectUuid, $environmentName);
                } catch (CoolifyApiException) {
                    // Environment not found
                }
            }

            // Get first application from the environment
            $app = null;
            if ($envResources && ! empty($envResources['applications'])) {
                $app = $envResources['applications'][0];
            }

            if ($app) {
                $appUuid = $app['uuid'] ?? null;

                // Determine if using deploy key (no GitHub App)
                $usesDeployKey = empty($app['source_id']) || $app['source_type'] === null;
                $webhookSecret = $app['manual_webhook_secret_github'] ?? null;

                // Build webhook URL if using deploy key
                $webhookUrl = null;
                $coolifyUrl = rtrim(config('coolify.url'), '/');
                if ($usesDeployKey && $webhookSecret && $appUuid) {
                    $webhookUrl = "{$coolifyUrl}/webhooks/source/github/events/manual?source={$appUuid}&webhook_secret={$webhookSecret}";
                }

                // Build proper Coolify dashboard URL
                $coolifyDashboardUrl = null;
                if ($projectUuid && $environmentUuid && $appUuid) {
                    $coolifyDashboardUrl = "{$coolifyUrl}/project/{$projectUuid}/{$environmentName}/application/{$appUuid}";
                }

                // Return ALL application data from Coolify, plus computed fields
                $stats['application'] = array_merge($app, [
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
                if ($appUuid) {
                    try {
                        $recentDeployments = $deployments->forApplication($appUuid);
                        $stats['recentDeployments'] = collect($recentDeployments)
                            ->take(10)
                            ->map(function ($d) {
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
                }
            }

            // Fetch databases from the environment resources
            $environmentId = $stats['environment']['id'] ?? null;

            if ($envResources) {
                // Extract databases from various type arrays in the environment
                // Returns first database of each category (primary = SQL databases, redis = cache)
                $databaseTypes = [
                    'postgresqls' => 'standalone-postgresql',
                    'mysqls' => 'standalone-mysql',
                    'mariadbs' => 'standalone-mariadb',
                    'mongodbs' => 'standalone-mongodb',
                    'redis' => 'standalone-redis',
                ];

                foreach ($databaseTypes as $key => $dbType) {
                    if (! empty($envResources[$key])) {
                        foreach ($envResources[$key] as $db) {
                            // Add database_type if not present (redis array doesn't have it)
                            if (empty($db['database_type'])) {
                                $db['database_type'] = $dbType;
                            }
                            $category = $this->getDatabaseCategory($db);

                            // Only set if not already set (take first of each category)
                            if (! isset($stats['databases'][$category])) {
                                $stats['databases'][$category] = $this->formatDatabaseInfo($db, $category);
                            }
                        }
                    }
                }

                // Fallback: check global databases for Dragonfly/KeyDB not in environment response
                // The Coolify API doesn't include Dragonfly in the redis array
                if ($environmentId && ! isset($stats['databases']['redis'])) {
                    try {
                        $allDatabases = $databases->all();
                        foreach ($allDatabases as $db) {
                            // Filter by environment_id
                            if (($db['environment_id'] ?? null) !== $environmentId) {
                                continue;
                            }
                            $category = $this->getDatabaseCategory($db);
                            if ($category === 'redis' && ! isset($stats['databases']['redis'])) {
                                $stats['databases']['redis'] = $this->formatDatabaseInfo($db, 'redis');
                                break;
                            }
                        }
                    } catch (CoolifyApiException) {
                        // Ignore database fetch errors
                    }
                }
            }

        } catch (CoolifyApiException) {
            $stats['connected'] = false;
        }

        return response()->json($stats);
    }

    /**
     * Determine database category (primary, redis, etc).
     */
    protected function getDatabaseCategory(array $db): string
    {
        $dbType = $db['database_type'] ?? '';

        if (str_contains($dbType, 'redis') || str_contains($dbType, 'dragonfly') || str_contains($dbType, 'keydb')) {
            return 'redis';
        }

        return 'primary';
    }

    /**
     * Format database info for the dashboard.
     */
    protected function formatDatabaseInfo(array $db, string $category): array
    {
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

        return array_merge($db, [
            'type' => $displayType,
            'category' => $category,
        ]);
    }
}
