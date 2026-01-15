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
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string|max:1000',
            'image' => 'sometimes|string|max:255',
            'is_public' => 'sometimes|boolean',
            'public_port' => 'sometimes|nullable|integer|min:1|max:65535',
            'limits_memory' => 'sometimes|string|max:20',
            'limits_cpu' => 'sometimes|string|max:20',
        ]);

        try {
            $result = $this->databases->update($uuid, $validated);

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
        $validated = $request->validate([
            'frequency' => ['required', 'string', 'max:100', 'regex:/^[\d\s\*\/\-\,]+$/'],
            'enabled' => 'sometimes|boolean',
            'save_s3' => 'sometimes|boolean',
            's3_storage_uuid' => 'sometimes|nullable|string|max:100',
            'databases_to_backup' => 'sometimes|nullable|string|max:1000',
            'database_backup_retention_amount_locally' => 'sometimes|integer|min:1|max:1000',
            'database_backup_retention_amount_s3' => 'sometimes|integer|min:1|max:1000',
        ]);

        try {
            $result = $this->databases->createBackup($uuid, $validated);

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
        $validated = $request->validate([
            'frequency' => ['sometimes', 'string', 'max:100', 'regex:/^[\d\s\*\/\-\,]+$/'],
            'enabled' => 'sometimes|boolean',
            'save_s3' => 'sometimes|boolean',
            's3_storage_uuid' => 'sometimes|nullable|string|max:100',
            'databases_to_backup' => 'sometimes|nullable|string|max:1000',
            'database_backup_retention_amount_locally' => 'sometimes|integer|min:1|max:1000',
            'database_backup_retention_amount_s3' => 'sometimes|integer|min:1|max:1000',
        ]);

        try {
            $result = $this->databases->updateBackup($uuid, $backupUuid, $validated);

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
