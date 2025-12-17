<?php

namespace Stumason\Coolify\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Stumason\Coolify\Contracts\ServiceRepository;
use Stumason\Coolify\Exceptions\CoolifyApiException;

class ServiceController extends Controller
{
    public function __construct(
        protected ServiceRepository $services
    ) {
        parent::__construct();
    }

    /**
     * List all services.
     */
    public function index(): JsonResponse
    {
        try {
            return response()->json($this->services->all());
        } catch (CoolifyApiException $throwable) {
            return response()->json(['error' => $throwable->getMessage()], $throwable->getCode() ?: 500);
        }
    }

    /**
     * Get service details.
     */
    public function show(string $uuid): JsonResponse
    {
        try {
            return response()->json($this->services->get($uuid));
        } catch (CoolifyApiException $throwable) {
            return response()->json(['error' => $throwable->getMessage()], $throwable->getCode() ?: 500);
        }
    }

    /**
     * Start a service.
     */
    public function start(string $uuid): JsonResponse
    {
        try {
            return response()->json($this->services->start($uuid));
        } catch (CoolifyApiException $throwable) {
            return response()->json(['error' => $throwable->getMessage()], $throwable->getCode() ?: 500);
        }
    }

    /**
     * Stop a service.
     */
    public function stop(string $uuid): JsonResponse
    {
        try {
            return response()->json($this->services->stop($uuid));
        } catch (CoolifyApiException $throwable) {
            return response()->json(['error' => $throwable->getMessage()], $throwable->getCode() ?: 500);
        }
    }

    /**
     * Restart a service.
     */
    public function restart(string $uuid): JsonResponse
    {
        try {
            return response()->json($this->services->restart($uuid));
        } catch (CoolifyApiException $throwable) {
            return response()->json(['error' => $throwable->getMessage()], $throwable->getCode() ?: 500);
        }
    }
}
