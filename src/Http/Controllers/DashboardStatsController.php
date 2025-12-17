<?php

namespace Stumason\Coolify\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Stumason\Coolify\Contracts\ApplicationRepository;
use Stumason\Coolify\Contracts\DatabaseRepository;
use Stumason\Coolify\Contracts\DeploymentRepository;
use Stumason\Coolify\CoolifyClient;
use Stumason\Coolify\Exceptions\CoolifyApiException;

class DashboardStatsController extends Controller
{
    /**
     * Get the key performance stats for the dashboard.
     */
    public function index(
        CoolifyClient $client,
        ApplicationRepository $applications,
        DatabaseRepository $databases,
        DeploymentRepository $deployments
    ): JsonResponse {
        $stats = [
            'connected' => false,
            'application' => null,
            'databases' => [],
            'recentDeployments' => [],
        ];

        try {
            // Test connection
            $stats['connected'] = $client->testConnection();

            if (! $stats['connected']) {
                return response()->json($stats);
            }

            // Get application status
            if ($uuid = config('coolify.application_uuid')) {
                try {
                    $app = $applications->get($uuid);
                    $stats['application'] = [
                        'uuid' => $app['uuid'] ?? null,
                        'name' => $app['name'] ?? 'Unknown',
                        'status' => $app['status'] ?? 'unknown',
                        'fqdn' => $app['fqdn'] ?? null,
                        'repository' => $app['git_repository'] ?? null,
                        'branch' => $app['git_branch'] ?? null,
                        'commit' => isset($app['git_commit_sha']) ? substr($app['git_commit_sha'], 0, 7) : null,
                    ];

                    // Get recent deployments
                    try {
                        $recentDeployments = $deployments->forApplication($uuid);
                        $stats['recentDeployments'] = collect($recentDeployments)
                            ->take(5)
                            ->map(fn ($d) => [
                                'uuid' => $d['uuid'] ?? null,
                                'status' => $d['status'] ?? 'unknown',
                                'commit' => isset($d['git_commit_sha']) ? substr($d['git_commit_sha'], 0, 7) : null,
                                'created_at' => $d['created_at'] ?? null,
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
            if ($dbUuid = config('coolify.resources.database')) {
                try {
                    $db = $databases->get($dbUuid);
                    $stats['databases']['primary'] = [
                        'uuid' => $db['uuid'] ?? null,
                        'name' => $db['name'] ?? 'Unknown',
                        'type' => $db['type'] ?? 'postgresql',
                        'status' => $db['status'] ?? 'unknown',
                    ];
                } catch (CoolifyApiException) {
                    // Database not found or error
                }
            }

            // Get redis status
            if ($redisUuid = config('coolify.resources.redis')) {
                try {
                    $redis = $databases->get($redisUuid);
                    $stats['databases']['redis'] = [
                        'uuid' => $redis['uuid'] ?? null,
                        'name' => $redis['name'] ?? 'Unknown',
                        'type' => $redis['type'] ?? 'redis',
                        'status' => $redis['status'] ?? 'unknown',
                    ];
                } catch (CoolifyApiException) {
                    // Redis not found or error
                }
            }

        } catch (CoolifyApiException) {
            $stats['connected'] = false;
        }

        return response()->json($stats);
    }
}
