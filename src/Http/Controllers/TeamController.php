<?php

namespace Stumason\Coolify\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Stumason\Coolify\Contracts\TeamRepository;
use Stumason\Coolify\Exceptions\CoolifyApiException;

class TeamController extends Controller
{
    public function __construct(
        protected TeamRepository $teams
    ) {
        parent::__construct();
    }

    /**
     * List all teams.
     */
    public function index(): JsonResponse
    {
        try {
            return response()->json($this->teams->all());
        } catch (CoolifyApiException $throwable) {
            return response()->json(['error' => $throwable->getMessage()], $throwable->getCode() ?: 500);
        }
    }

    /**
     * Get the current team.
     */
    public function current(): JsonResponse
    {
        try {
            return response()->json($this->teams->current());
        } catch (CoolifyApiException $throwable) {
            return response()->json(['error' => $throwable->getMessage()], $throwable->getCode() ?: 500);
        }
    }

    /**
     * Get team members.
     */
    public function members(): JsonResponse
    {
        try {
            return response()->json($this->teams->members());
        } catch (CoolifyApiException $throwable) {
            return response()->json(['error' => $throwable->getMessage()], $throwable->getCode() ?: 500);
        }
    }
}
