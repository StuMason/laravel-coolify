<?php

namespace Stumason\Coolify\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Stumason\Coolify\Contracts\DatabaseRepository;
use Stumason\Coolify\Exceptions\CoolifyApiException;

class DatabaseController extends Controller
{
    public function __construct(
        protected DatabaseRepository $databases
    ) {
        parent::__construct();
    }

    /**
     * Get all databases.
     */
    public function index(): JsonResponse
    {
        try {
            return response()->json($this->databases->all());
        } catch (CoolifyApiException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    /**
     * Get database details.
     */
    public function show(string $uuid): JsonResponse
    {
        try {
            return response()->json($this->databases->get($uuid));
        } catch (CoolifyApiException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    /**
     * Restart the database.
     */
    public function restart(string $uuid): JsonResponse
    {
        try {
            return response()->json($this->databases->restart($uuid));
        } catch (CoolifyApiException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    /**
     * Create a backup.
     */
    public function backup(string $uuid): JsonResponse
    {
        try {
            return response()->json($this->databases->backup($uuid));
        } catch (CoolifyApiException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    /**
     * Get backup history.
     */
    public function backups(string $uuid): JsonResponse
    {
        try {
            return response()->json($this->databases->backups($uuid));
        } catch (CoolifyApiException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }
}
