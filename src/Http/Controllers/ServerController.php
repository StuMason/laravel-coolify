<?php

namespace Stumason\Coolify\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Stumason\Coolify\Contracts\ServerRepository;
use Stumason\Coolify\Exceptions\CoolifyApiException;

class ServerController extends Controller
{
    public function __construct(
        protected ServerRepository $servers
    ) {
        parent::__construct();
    }

    /**
     * List all servers.
     */
    public function index(): JsonResponse
    {
        try {
            return response()->json($this->servers->all());
        } catch (CoolifyApiException $throwable) {
            return response()->json(['error' => $throwable->getMessage()], $throwable->getCode() ?: 500);
        }
    }

    /**
     * Get server details.
     */
    public function show(string $uuid): JsonResponse
    {
        try {
            return response()->json($this->servers->get($uuid));
        } catch (CoolifyApiException $throwable) {
            return response()->json(['error' => $throwable->getMessage()], $throwable->getCode() ?: 500);
        }
    }

    /**
     * Get server resources (applications, databases, services).
     */
    public function resources(string $uuid): JsonResponse
    {
        try {
            return response()->json($this->servers->resources($uuid));
        } catch (CoolifyApiException $throwable) {
            return response()->json(['error' => $throwable->getMessage()], $throwable->getCode() ?: 500);
        }
    }

    /**
     * Get server domains.
     */
    public function domains(string $uuid): JsonResponse
    {
        try {
            return response()->json($this->servers->domains($uuid));
        } catch (CoolifyApiException $throwable) {
            return response()->json(['error' => $throwable->getMessage()], $throwable->getCode() ?: 500);
        }
    }

    /**
     * Validate server connection.
     */
    public function validate(string $uuid): JsonResponse
    {
        try {
            return response()->json($this->servers->validate($uuid));
        } catch (CoolifyApiException $throwable) {
            return response()->json(['error' => $throwable->getMessage()], $throwable->getCode() ?: 500);
        }
    }
}
