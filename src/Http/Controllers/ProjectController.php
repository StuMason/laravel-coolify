<?php

namespace Stumason\Coolify\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Stumason\Coolify\Contracts\ProjectRepository;
use Stumason\Coolify\Exceptions\CoolifyApiException;

class ProjectController extends Controller
{
    public function __construct(
        protected ProjectRepository $projects
    ) {
        parent::__construct();
    }

    /**
     * List all projects.
     */
    public function index(): JsonResponse
    {
        try {
            return response()->json($this->projects->all());
        } catch (CoolifyApiException $throwable) {
            return response()->json(['error' => $throwable->getMessage()], $throwable->getCode() ?: 500);
        }
    }

    /**
     * Get project details.
     */
    public function show(string $uuid): JsonResponse
    {
        try {
            return response()->json($this->projects->get($uuid));
        } catch (CoolifyApiException $throwable) {
            return response()->json(['error' => $throwable->getMessage()], $throwable->getCode() ?: 500);
        }
    }

    /**
     * Get project environments.
     */
    public function environments(string $uuid): JsonResponse
    {
        try {
            return response()->json($this->projects->environments($uuid));
        } catch (CoolifyApiException $throwable) {
            return response()->json(['error' => $throwable->getMessage()], $throwable->getCode() ?: 500);
        }
    }

    /**
     * Get a specific environment.
     */
    public function environment(string $projectUuid, string $environmentName): JsonResponse
    {
        try {
            return response()->json($this->projects->environment($projectUuid, $environmentName));
        } catch (CoolifyApiException $throwable) {
            return response()->json(['error' => $throwable->getMessage()], $throwable->getCode() ?: 500);
        }
    }
}
