<?php

namespace Stumason\Coolify\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stumason\Coolify\Contracts\ApplicationRepository;
use Stumason\Coolify\Exceptions\CoolifyApiException;

class ApplicationController extends Controller
{
    public function __construct(
        protected ApplicationRepository $applications
    ) {
        parent::__construct();
    }

    /**
     * Get application details.
     */
    public function show(string $uuid): JsonResponse
    {
        try {
            return response()->json($this->applications->get($uuid));
        } catch (CoolifyApiException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    /**
     * Update application settings.
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string|max:1000',
            'fqdn' => 'sometimes|nullable|string|max:255',
            'git_repository' => 'sometimes|string|max:500',
            'git_branch' => 'sometimes|string|max:255',
            'git_commit_sha' => 'sometimes|string|max:40',
            'build_pack' => 'sometimes|string|in:nixpacks,dockerfile,dockercompose,static',
            'ports_exposes' => 'sometimes|string|max:100',
            'health_check_enabled' => 'sometimes|boolean',
            'health_check_path' => 'sometimes|string|max:255',
            'health_check_port' => 'sometimes|nullable|integer|min:1|max:65535',
            'health_check_interval' => 'sometimes|integer|min:5|max:3600',
            'health_check_timeout' => 'sometimes|integer|min:1|max:300',
            'health_check_retries' => 'sometimes|integer|min:1|max:10',
            'health_check_start_period' => 'sometimes|integer|min:0|max:300',
        ]);

        try {
            $result = $this->applications->update($uuid, $validated);

            return response()->json($result);
        } catch (CoolifyApiException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    /**
     * Deploy the application.
     *
     * Accepts optional parameters:
     * - force: boolean - Force rebuild without cache
     * - commit: string - Specific commit SHA to deploy
     */
    public function deploy(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'force' => 'sometimes|boolean',
            'commit' => 'sometimes|nullable|string|regex:/^[a-f0-9]{7,40}$/i',
        ]);

        try {
            $force = $validated['force'] ?? false;
            $commit = $validated['commit'] ?? null;

            $result = $this->applications->deploy($uuid, $force, $commit);

            return response()->json($result);
        } catch (CoolifyApiException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    /**
     * Restart the application.
     */
    public function restart(string $uuid): JsonResponse
    {
        try {
            $result = $this->applications->restart($uuid);

            return response()->json($result);
        } catch (CoolifyApiException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    /**
     * Stop the application.
     */
    public function stop(string $uuid): JsonResponse
    {
        try {
            $result = $this->applications->stop($uuid);

            return response()->json($result);
        } catch (CoolifyApiException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    /**
     * Start the application.
     */
    public function start(string $uuid): JsonResponse
    {
        try {
            $result = $this->applications->start($uuid);

            return response()->json($result);
        } catch (CoolifyApiException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    /**
     * Get application logs.
     */
    public function logs(Request $request, string $uuid): JsonResponse
    {
        try {
            $lines = $request->integer('lines', 100);
            $result = $this->applications->logs($uuid, $lines);

            return response()->json($result);
        } catch (CoolifyApiException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    /**
     * Get application environment variables.
     */
    public function envs(string $uuid): JsonResponse
    {
        try {
            return response()->json($this->applications->envs($uuid));
        } catch (CoolifyApiException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    /**
     * Create an environment variable.
     */
    public function createEnv(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:255', 'regex:/^[A-Z][A-Z0-9_]*$/'],
            'value' => 'required|string|max:65535',
            'is_preview' => 'sometimes|boolean',
            'is_build_time' => 'sometimes|boolean',
            'is_literal' => 'sometimes|boolean',
            'is_multiline' => 'sometimes|boolean',
            'is_shown_once' => 'sometimes|boolean',
        ]);

        try {
            $result = $this->applications->createEnv($uuid, $validated);

            return response()->json($result);
        } catch (CoolifyApiException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    /**
     * Update an environment variable.
     */
    public function updateEnv(Request $request, string $uuid, string $envUuid): JsonResponse
    {
        $validated = $request->validate([
            'key' => ['sometimes', 'string', 'max:255', 'regex:/^[A-Z][A-Z0-9_]*$/'],
            'value' => 'sometimes|string|max:65535',
            'is_preview' => 'sometimes|boolean',
            'is_build_time' => 'sometimes|boolean',
            'is_literal' => 'sometimes|boolean',
            'is_multiline' => 'sometimes|boolean',
            'is_shown_once' => 'sometimes|boolean',
        ]);

        try {
            $result = $this->applications->updateEnv($uuid, $envUuid, $validated);

            return response()->json($result);
        } catch (CoolifyApiException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    /**
     * Delete an environment variable.
     */
    public function deleteEnv(string $uuid, string $envUuid): JsonResponse
    {
        try {
            $this->applications->deleteEnv($uuid, $envUuid);

            return response()->json(['success' => true]);
        } catch (CoolifyApiException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }
}
