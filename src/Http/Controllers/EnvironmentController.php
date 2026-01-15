<?php

namespace Stumason\Coolify\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Stumason\Coolify\Models\CoolifyResource;

class EnvironmentController extends Controller
{
    /**
     * List all configured environments.
     */
    public function index(): JsonResponse
    {
        $environments = CoolifyResource::query()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn ($env) => [
                'id' => $env->id,
                'name' => $env->name,
                'environment' => $env->environment,
                'is_default' => $env->is_default,
                'application_uuid' => $env->application_uuid,
                'database_uuid' => $env->database_uuid,
                'redis_uuid' => $env->redis_uuid,
                'repository' => $env->repository,
                'branch' => $env->branch,
            ]);

        return response()->json($environments);
    }

    /**
     * Switch to a different environment.
     */
    public function switch(int $id): JsonResponse
    {
        $resource = CoolifyResource::query()->findOrFail($id);
        $resource->setAsDefault();

        return response()->json([
            'success' => true,
            'message' => "Switched to {$resource->name}",
            'environment' => [
                'id' => $resource->id,
                'name' => $resource->name,
                'environment' => $resource->environment,
                'is_default' => true,
            ],
        ]);
    }
}
