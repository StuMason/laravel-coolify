<?php

namespace Stumason\Coolify\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Stumason\Coolify\Exceptions\CoolifyApiException;
use Stumason\Coolify\Services\CoolifyProjectService;

class EnvironmentController extends Controller
{
    /**
     * List all environments from the configured Coolify project.
     */
    public function index(CoolifyProjectService $projectService): JsonResponse
    {
        $environments = $projectService->getEnvironments();
        $currentEnvironment = $projectService->getEnvironment();

        $result = collect($environments)->map(fn ($env) => [
            'id' => $env['id'] ?? null,
            'name' => $env['name'] ?? 'unknown',
            'environment' => $env['name'] ?? 'unknown',
            'is_default' => ($env['name'] ?? '') === $currentEnvironment,
            'description' => $env['description'] ?? null,
        ]);

        return response()->json($result);
    }

    /**
     * Get details about a specific environment.
     *
     * Note: Environment switching is now done via config/env vars, not database.
     * This endpoint returns environment details for informational purposes.
     */
    public function show(string $name, CoolifyProjectService $projectService): JsonResponse
    {
        $projectUuid = $projectService->getProjectUuid();

        if (! $projectUuid) {
            return response()->json([
                'error' => 'Project UUID not configured',
            ], 400);
        }

        try {
            $projects = app(\Stumason\Coolify\Contracts\ProjectRepository::class);
            $environment = $projects->environment($projectUuid, $name);

            return response()->json([
                'success' => true,
                'environment' => $environment,
            ]);
        } catch (CoolifyApiException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 404);
        }
    }
}
