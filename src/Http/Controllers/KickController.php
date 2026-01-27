<?php

declare(strict_types=1);

namespace Stumason\Coolify\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stumason\Coolify\Contracts\KickRepository;

/**
 * Controller for Laravel Kick integration endpoints.
 *
 * Proxies requests to remote Laravel Kick endpoints on applications
 * managed by Coolify.
 */
class KickController extends Controller
{
    public function __construct(
        protected KickRepository $kick,
    ) {
        parent::__construct();
    }

    /**
     * Get kick status for an application.
     * Returns availability and configuration status.
     */
    public function status(string $appUuid): JsonResponse
    {
        $config = $this->kick->getConfig($appUuid);

        if (! $config) {
            return response()->json([
                'available' => false,
                'reason' => 'not_configured',
                'message' => 'Laravel Kick is not configured for this application. Add KICK_TOKEN and KICK_ENABLED=true to the app environment variables.',
            ]);
        }

        $reachable = $this->kick->isReachable($appUuid);

        return response()->json([
            'available' => $reachable,
            'reason' => $reachable ? null : 'unreachable',
            'message' => $reachable ? null : 'Kick endpoints are not responding. Check that laravel-kick is installed and the token is correct.',
        ]);
    }

    /**
     * Get health check status from kick.
     */
    public function health(string $appUuid): JsonResponse
    {
        $data = $this->kick->health($appUuid);

        return $data
            ? response()->json($data)
            : response()->json(['error' => 'Kick not available'], 503);
    }

    /**
     * Get system stats from kick.
     */
    public function stats(string $appUuid): JsonResponse
    {
        $data = $this->kick->stats($appUuid);

        return $data
            ? response()->json($data)
            : response()->json(['error' => 'Kick not available'], 503);
    }

    /**
     * List available log files.
     */
    public function logFiles(string $appUuid): JsonResponse
    {
        $data = $this->kick->logFiles($appUuid);

        return $data
            ? response()->json($data)
            : response()->json(['error' => 'Kick not available'], 503);
    }

    /**
     * Read log entries from a file.
     */
    public function logRead(Request $request, string $appUuid, string $file): JsonResponse
    {
        $level = $request->query('level');
        $search = $request->query('search');
        $lines = $request->integer('lines', 100);

        $data = $this->kick->logRead($appUuid, $file, $level, $search, $lines);

        return $data
            ? response()->json($data)
            : response()->json(['error' => 'Kick not available'], 503);
    }

    /**
     * Get queue status.
     */
    public function queueStatus(string $appUuid): JsonResponse
    {
        $data = $this->kick->queueStatus($appUuid);

        return $data
            ? response()->json($data)
            : response()->json(['error' => 'Kick not available'], 503);
    }

    /**
     * Get failed jobs.
     */
    public function queueFailed(Request $request, string $appUuid): JsonResponse
    {
        $limit = $request->integer('limit', 20);

        $data = $this->kick->queueFailed($appUuid, $limit);

        return $data
            ? response()->json($data)
            : response()->json(['error' => 'Kick not available'], 503);
    }

    /**
     * Write test log entries.
     */
    public function logsTest(string $appUuid): JsonResponse
    {
        $data = $this->kick->logsTest($appUuid);

        return $data
            ? response()->json($data)
            : response()->json(['error' => 'Kick not available'], 503);
    }

    /**
     * List available artisan commands.
     */
    public function artisanList(string $appUuid): JsonResponse
    {
        $data = $this->kick->artisanList($appUuid);

        return $data
            ? response()->json($data)
            : response()->json(['error' => 'Kick not available'], 503);
    }

    /**
     * Execute an artisan command.
     */
    public function artisanRun(Request $request, string $appUuid): JsonResponse
    {
        $validated = $request->validate([
            'command' => ['required', 'string'],
            'arguments' => ['array'],
        ]);

        $data = $this->kick->artisanRun(
            $appUuid,
            $validated['command'],
            $validated['arguments'] ?? [],
        );

        return $data
            ? response()->json($data)
            : response()->json(['error' => 'Kick not available'], 503);
    }
}
