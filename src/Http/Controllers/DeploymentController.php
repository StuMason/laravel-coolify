<?php

namespace Stumason\Coolify\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Stumason\Coolify\Contracts\DeploymentRepository;
use Stumason\Coolify\Exceptions\CoolifyApiException;

class DeploymentController extends Controller
{
    public function __construct(
        protected DeploymentRepository $deployments
    ) {
        parent::__construct();
    }

    /**
     * Get all deployments for an application.
     */
    public function index(string $applicationUuid): JsonResponse
    {
        try {
            return response()->json($this->deployments->forApplication($applicationUuid));
        } catch (CoolifyApiException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    /**
     * Get deployment details.
     */
    public function show(string $uuid): JsonResponse
    {
        try {
            return response()->json($this->deployments->get($uuid));
        } catch (CoolifyApiException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    /**
     * Get deployment logs.
     */
    public function logs(string $uuid): JsonResponse
    {
        try {
            return response()->json($this->deployments->logs($uuid));
        } catch (CoolifyApiException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    /**
     * Cancel a deployment.
     */
    public function cancel(string $uuid): JsonResponse
    {
        try {
            return response()->json($this->deployments->cancel($uuid));
        } catch (CoolifyApiException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }
}
