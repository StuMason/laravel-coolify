<?php

namespace Stumason\Coolify;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Stumason\Coolify\Contracts\ApplicationRepository;
use Stumason\Coolify\Contracts\DatabaseRepository;
use Stumason\Coolify\Contracts\DeploymentRepository;
use Stumason\Coolify\Contracts\ProjectRepository;
use Stumason\Coolify\Contracts\ServerRepository;
use Stumason\Coolify\Contracts\ServiceRepository;

class Coolify
{
    /**
     * The callback that should be used to authenticate Coolify dashboard users.
     */
    public static ?Closure $authUsing = null;

    /**
     * The email address for notifications.
     */
    public static ?string $email = null;

    /**
     * Indicates if Coolify routes have been registered.
     */
    public static bool $registeredRoutes = false;

    /**
     * Determine if the given request can access the Coolify dashboard.
     */
    public static function check(mixed $request): bool
    {
        return (static::$authUsing ?? function () {
            return app()->environment('local');
        })($request);
    }

    /**
     * Set the callback that should be used to authenticate Coolify dashboard users.
     */
    public static function auth(Closure $callback): self
    {
        static::$authUsing = $callback;

        return new self;
    }

    /**
     * Get the application repository instance.
     */
    public static function applications(): ApplicationRepository
    {
        return app(ApplicationRepository::class);
    }

    /**
     * Get the database repository instance.
     */
    public static function databases(): DatabaseRepository
    {
        return app(DatabaseRepository::class);
    }

    /**
     * Get the deployment repository instance.
     */
    public static function deployments(): DeploymentRepository
    {
        return app(DeploymentRepository::class);
    }

    /**
     * Get the server repository instance.
     */
    public static function servers(): ServerRepository
    {
        return app(ServerRepository::class);
    }

    /**
     * Get the service repository instance.
     */
    public static function services(): ServiceRepository
    {
        return app(ServiceRepository::class);
    }

    /**
     * Get the project repository instance.
     */
    public static function projects(): ProjectRepository
    {
        return app(ProjectRepository::class);
    }

    /**
     * Get the application UUID for this project by matching git repository.
     * Fetches from Coolify and caches the result.
     */
    public static function getApplicationUuid(): ?string
    {
        $projectUuid = config('coolify.project_uuid');
        if (! $projectUuid) {
            return null;
        }

        $gitRepo = static::getCurrentGitRepository();
        if (! $gitRepo) {
            return null;
        }

        // Include git repo in cache key to prevent stale cache when switching repos
        $cacheKey = "coolify.app_uuid.{$projectUuid}.".md5($gitRepo);
        $ttl = config('coolify.cache_ttl', 30);

        return Cache::remember($cacheKey, $ttl, function () use ($gitRepo) {
            // Fetch all applications and find the one matching our repository
            $applications = static::applications()->all();

            foreach ($applications as $app) {
                $appRepo = $app['git_repository'] ?? '';
                // Normalize: strip .git suffix and compare
                $normalizedAppRepo = preg_replace('/\.git$/', '', $appRepo);
                $normalizedAppRepo = preg_replace('#^git@github\.com:#', '', $normalizedAppRepo);
                $normalizedAppRepo = preg_replace('#^https?://github\.com/#', '', $normalizedAppRepo);

                if (strcasecmp($normalizedAppRepo, $gitRepo) === 0) {
                    return $app['uuid'] ?? null;
                }
            }

            return null;
        });
    }

    /**
     * Get the current git repository name (owner/repo format).
     *
     * Note: Currently only supports GitHub repositories. GitLab, Bitbucket,
     * and other providers will return null and require using --uuid flag.
     */
    public static function getCurrentGitRepository(): ?string
    {
        // Validate we're in a git repository first
        if (! is_dir(base_path('.git'))) {
            return null;
        }

        $result = Process::run('git remote get-url origin 2>/dev/null');

        if (! $result->successful() || empty(trim($result->output()))) {
            return null;
        }

        $remoteUrl = trim($result->output());

        // Extract owner/repo from various formats
        if (preg_match('#github\.com[:/]([^/]+/[^/]+?)(?:\.git)?$#', $remoteUrl, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Deploy the current application.
     *
     * @throws \InvalidArgumentException
     */
    public static function deploy(?string $uuid = null): array
    {
        $uuid = $uuid ?? static::getApplicationUuid();

        if ($uuid === null) {
            throw new \InvalidArgumentException('No application UUID configured. Run coolify:provision first or provide a UUID.');
        }

        return static::applications()->deploy($uuid);
    }

    /**
     * Get the status of the current application.
     *
     * @throws \InvalidArgumentException
     */
    public static function status(?string $uuid = null): array
    {
        $uuid = $uuid ?? static::getApplicationUuid();

        if ($uuid === null) {
            throw new \InvalidArgumentException('No application UUID configured. Run coolify:provision first or provide a UUID.');
        }

        return static::applications()->get($uuid);
    }

    /**
     * Get the logs for the current application.
     *
     * @throws \InvalidArgumentException
     */
    public static function logs(?string $uuid = null): array
    {
        $uuid = $uuid ?? static::getApplicationUuid();

        if ($uuid === null) {
            throw new \InvalidArgumentException('No application UUID configured. Run coolify:provision first or provide a UUID.');
        }

        return static::applications()->logs($uuid);
    }

    /**
     * Specify the email address to which notifications should be routed.
     */
    public static function routeMailNotificationsTo(string $email): self
    {
        static::$email = $email;

        return new self;
    }
}
