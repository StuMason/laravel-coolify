<?php

namespace Stumason\Coolify\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Stumason\Coolify\Contracts\ProjectRepository;
use Stumason\Coolify\Exceptions\CoolifyApiException;

class EnvironmentController extends Controller
{
    /**
     * List all environments for the configured project.
     */
    public function index(ProjectRepository $projects): JsonResponse
    {
        $projectUuid = config('coolify.project_uuid');

        if (! $projectUuid) {
            return response()->json([]);
        }

        try {
            $project = $projects->get($projectUuid);

            return response()->json($project['environments'] ?? []);
        } catch (CoolifyApiException) {
            return response()->json([]);
        }
    }
}
