<?php

namespace Stumason\Coolify\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Stumason\Coolify\Contracts\ApplicationRepository;
use Stumason\Coolify\Contracts\DatabaseRepository;
use Stumason\Coolify\Contracts\DeploymentRepository;
use Stumason\Coolify\Contracts\SecurityKeyRepository;
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

            // Get deploy key info if configured
            if ($deployKeyUuid = config('coolify.deploy_key_uuid')) {
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
            if ($uuid = config('coolify.application_uuid')) {
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
                    $projectUuid = $app['project']['uuid'] ?? config('coolify.project_uuid');
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
