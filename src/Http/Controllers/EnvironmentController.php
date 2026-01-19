<?php

namespace Stumason\Coolify\Http\Controllers;

use Illuminate\Http\JsonResponse;

class EnvironmentController extends Controller
{
    /**
     * List all configured environments.
     *
     * Note: Multi-environment support has been removed.
     * Use COOLIFY_PROJECT_UUID in .env to configure your project.
     */
    public function index(): JsonResponse
    {
        return response()->json([]);
    }

    /**
     * Switch to a different environment.
     *
     * Note: Multi-environment support has been removed.
     */
    public function switch(int $id): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Multi-environment support has been removed. Configure COOLIFY_PROJECT_UUID in .env instead.',
        ], 400);
    }
}
