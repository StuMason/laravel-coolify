<?php

namespace Stumason\Coolify\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Stumason\Coolify\Contracts\ApplicationRepository;
use Stumason\Coolify\Contracts\DatabaseRepository;
use Stumason\Coolify\Contracts\DeploymentRepository;
use Stumason\Coolify\Contracts\ServerRepository;
use Stumason\Coolify\Contracts\ServiceRepository;
use Stumason\Coolify\Exceptions\CoolifyApiException;

class HomeController extends Controller
{
    public function __construct(
        protected ApplicationRepository $applications,
        protected ServerRepository $servers,
        protected DatabaseRepository $databases,
        protected DeploymentRepository $deployments,
        protected ServiceRepository $services
    ) {
        parent::__construct();
    }

    /**
     * Display the Coolify dashboard.
     */
    public function index(Request $request): Response
    {
        $path = trim($request->path(), '/');

        // Route to the appropriate Inertia page based on path
        return match (true) {
            $path === 'coolify' || $path === '' => $this->dashboard(),
            str_starts_with($path, 'coolify/applications/') => $this->applicationShow($this->extractUuid($path)),
            $path === 'coolify/applications' => $this->applicationsIndex(),
            str_starts_with($path, 'coolify/servers/') => $this->serverShow($this->extractUuid($path)),
            $path === 'coolify/servers' => $this->serversIndex(),
            str_starts_with($path, 'coolify/databases/') => $this->databaseShow($this->extractUuid($path)),
            $path === 'coolify/databases' => $this->databasesIndex(),
            str_starts_with($path, 'coolify/services/') => $this->serviceShow($this->extractUuid($path)),
            $path === 'coolify/services' => $this->servicesIndex(),
            str_starts_with($path, 'coolify/deployments/') => $this->deploymentShow($this->extractUuid($path)),
            $path === 'coolify/deployments' => $this->deploymentsIndex(),
            default => $this->dashboard(),
        };
    }

    /**
     * Display the main dashboard.
     */
    protected function dashboard(): Response
    {
        try {
            $applications = $this->applications->all();
            $servers = $this->servers->all();
            $databases = $this->databases->all();
            $recentDeployments = $this->deployments->all();

            $stats = [
                'applications' => [
                    'total' => count($applications),
                    'running' => count(array_filter($applications, fn ($app) => ($app['status'] ?? '') === 'running')),
                    'stopped' => count(array_filter($applications, fn ($app) => ($app['status'] ?? '') === 'stopped')),
                    'error' => count(array_filter($applications, fn ($app) => in_array($app['status'] ?? '', ['error', 'degraded']))),
                ],
                'servers' => [
                    'total' => count($servers),
                    'reachable' => count(array_filter($servers, fn ($server) => $server['is_reachable'] ?? false)),
                    'unreachable' => count(array_filter($servers, fn ($server) => ! ($server['is_reachable'] ?? true))),
                ],
                'databases' => [
                    'total' => count($databases),
                    'running' => count(array_filter($databases, fn ($db) => ($db['status'] ?? '') === 'running')),
                ],
                'deployments' => [
                    'total' => count($recentDeployments),
                    'recent' => array_slice($recentDeployments, 0, 10),
                ],
            ];
        } catch (CoolifyApiException $throwable) {
            $stats = $this->emptyStats();
        }

        return Inertia::render('dashboard', [
            'stats' => $stats,
            'pollingInterval' => config('coolify.polling_interval', 10),
        ]);
    }

    /**
     * Display applications index.
     */
    protected function applicationsIndex(): Response
    {
        try {
            $applications = $this->applications->all();
        } catch (CoolifyApiException $throwable) {
            $applications = [];
        }

        return Inertia::render('applications/index', [
            'applications' => $applications,
        ]);
    }

    /**
     * Display a single application.
     */
    protected function applicationShow(string $uuid): Response
    {
        try {
            $application = $this->applications->get($uuid);
            $deployments = $this->deployments->forApplication($uuid);
            $logs = $this->applications->logs($uuid)['logs'] ?? '';
        } catch (CoolifyApiException $throwable) {
            $application = [];
            $deployments = [];
            $logs = '';
        }

        return Inertia::render('applications/show', [
            'application' => $application,
            'deployments' => $deployments,
            'logs' => $logs,
            'pollingInterval' => config('coolify.polling_interval', 10),
        ]);
    }

    /**
     * Display servers index.
     */
    protected function serversIndex(): Response
    {
        try {
            $servers = $this->servers->all();
        } catch (CoolifyApiException $throwable) {
            $servers = [];
        }

        return Inertia::render('servers/index', [
            'servers' => $servers,
        ]);
    }

    /**
     * Display a single server.
     */
    protected function serverShow(string $uuid): Response
    {
        try {
            $server = $this->servers->get($uuid);
            $resources = $this->servers->resources($uuid);
            $domains = $this->servers->domains($uuid);
        } catch (CoolifyApiException $throwable) {
            $server = [];
            $resources = ['applications' => [], 'databases' => []];
            $domains = [];
        }

        return Inertia::render('servers/show', [
            'server' => $server,
            'resources' => $resources,
            'domains' => $domains,
        ]);
    }

    /**
     * Display databases index.
     */
    protected function databasesIndex(): Response
    {
        try {
            $databases = $this->databases->all();
        } catch (CoolifyApiException $throwable) {
            $databases = [];
        }

        return Inertia::render('databases/index', [
            'databases' => $databases,
        ]);
    }

    /**
     * Display a single database.
     */
    protected function databaseShow(string $uuid): Response
    {
        try {
            $database = $this->databases->get($uuid);
            $backups = $this->databases->backups($uuid);
        } catch (CoolifyApiException $throwable) {
            $database = [];
            $backups = [];
        }

        return Inertia::render('databases/show', [
            'database' => $database,
            'backups' => $backups,
        ]);
    }

    /**
     * Display services index.
     */
    protected function servicesIndex(): Response
    {
        try {
            $services = $this->services->all();
        } catch (CoolifyApiException $throwable) {
            $services = [];
        }

        return Inertia::render('services/index', [
            'services' => $services,
        ]);
    }

    /**
     * Display a single service.
     */
    protected function serviceShow(string $uuid): Response
    {
        try {
            $service = $this->services->get($uuid);
        } catch (CoolifyApiException $throwable) {
            $service = [];
        }

        return Inertia::render('services/show', [
            'service' => $service,
        ]);
    }

    /**
     * Display deployments index.
     */
    protected function deploymentsIndex(): Response
    {
        try {
            $deployments = $this->deployments->all();
        } catch (CoolifyApiException $throwable) {
            $deployments = [];
        }

        return Inertia::render('deployments/index', [
            'deployments' => $deployments,
        ]);
    }

    /**
     * Display a single deployment.
     */
    protected function deploymentShow(string $uuid): Response
    {
        try {
            $deployment = $this->deployments->get($uuid);
            $applicationUuid = $deployment['application_uuid'] ?? config('coolify.application_uuid');
            $application = $applicationUuid ? $this->applications->get($applicationUuid) : [];
            $logs = $this->deployments->logs($uuid)['logs'] ?? '';
        } catch (CoolifyApiException $throwable) {
            $deployment = [];
            $application = [];
            $logs = '';
        }

        return Inertia::render('deployments/show', [
            'deployment' => $deployment,
            'application' => $application,
            'logs' => $logs,
            'pollingInterval' => config('coolify.polling_interval', 10),
        ]);
    }

    /**
     * Extract UUID from path.
     */
    protected function extractUuid(string $path): string
    {
        $parts = explode('/', $path);

        return end($parts);
    }

    /**
     * Get empty stats structure.
     *
     * @return array<string, mixed>
     */
    protected function emptyStats(): array
    {
        return [
            'applications' => ['total' => 0, 'running' => 0, 'stopped' => 0, 'error' => 0],
            'servers' => ['total' => 0, 'reachable' => 0, 'unreachable' => 0],
            'databases' => ['total' => 0, 'running' => 0],
            'deployments' => ['total' => 0, 'recent' => []],
        ];
    }
}
