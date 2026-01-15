<?php

namespace Stumason\Coolify\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
     * Update database settings.
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        try {
            $result = $this->databases->update($uuid, $request->all());

            return response()->json($result);
        } catch (CoolifyApiException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    /**
     * Start the database.
     */
    public function start(string $uuid): JsonResponse
    {
        try {
            return response()->json($this->databases->start($uuid));
        } catch (CoolifyApiException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    /**
     * Stop the database.
     */
    public function stop(string $uuid): JsonResponse
    {
        try {
            return response()->json($this->databases->stop($uuid));
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
     * Get backup schedules and executions.
     */
    public function backups(string $uuid): JsonResponse
    {
        try {
            return response()->json($this->databases->backups($uuid));
        } catch (CoolifyApiException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    /**
     * Create a backup schedule.
     */
    public function createBackup(Request $request, string $uuid): JsonResponse
    {
        try {
            $result = $this->databases->createBackup($uuid, $request->all());

            return response()->json($result);
        } catch (CoolifyApiException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    /**
     * Update a backup schedule.
     */
    public function updateBackup(Request $request, string $uuid, string $backupUuid): JsonResponse
    {
        try {
            $result = $this->databases->updateBackup($uuid, $backupUuid, $request->all());

            return response()->json($result);
        } catch (CoolifyApiException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    /**
     * Delete a backup schedule.
     */
    public function deleteBackup(string $uuid, string $backupUuid): JsonResponse
    {
        try {
            $this->databases->deleteBackup($uuid, $backupUuid);

            return response()->json(['success' => true]);
        } catch (CoolifyApiException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }
}
