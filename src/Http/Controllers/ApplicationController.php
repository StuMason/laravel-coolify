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
     * Deploy the application.
     */
    public function deploy(Request $request, string $uuid): JsonResponse
    {
        try {
            $result = $this->applications->deploy($uuid);

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
}
