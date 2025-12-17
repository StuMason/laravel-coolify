<?php

namespace Stumason\Coolify;

use Closure;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Js;
use RuntimeException;
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
     * The Slack notifications webhook URL.
     */
    public static ?string $slackWebhookUrl = null;

    /**
     * The Slack notifications channel.
     */
    public static ?string $slackChannel = null;

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
    public static function auth(Closure $callback): static
    {
        static::$authUsing = $callback;

        return new static;
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
     * Deploy the current application.
     */
    public static function deploy(?string $uuid = null): array
    {
        $uuid = $uuid ?? config('coolify.application_uuid');

        return static::applications()->deploy($uuid);
    }

    /**
     * Get the status of the current application.
     */
    public static function status(?string $uuid = null): array
    {
        $uuid = $uuid ?? config('coolify.application_uuid');

        return static::applications()->get($uuid);
    }

    /**
     * Get the logs for the current application.
     */
    public static function logs(?string $uuid = null): array
    {
        $uuid = $uuid ?? config('coolify.application_uuid');

        return static::applications()->logs($uuid);
    }

    /**
     * Specify the email address to which notifications should be routed.
     */
    public static function routeMailNotificationsTo(string $email): static
    {
        static::$email = $email;

        return new static;
    }

    /**
     * Specify the webhook URL and channel for Slack notifications.
     */
    public static function routeSlackNotificationsTo(string $url, ?string $channel = null): static
    {
        static::$slackWebhookUrl = $url;
        static::$slackChannel = $channel;

        return new static;
    }

    /**
     * Get the default JavaScript variables for the Coolify dashboard.
     *
     * @return array<string, mixed>
     */
    public static function scriptVariables(): array
    {
        return [
            'path' => config('coolify.path'),
            'pollingInterval' => config('coolify.polling_interval', 10) * 1000,
        ];
    }

    /**
     * Get the CSS for the Coolify dashboard.
     */
    public static function css(): HtmlString
    {
        $cssPath = __DIR__.'/../dist/app.css';

        if (! file_exists($cssPath)) {
            throw new RuntimeException('Unable to load the Coolify dashboard CSS. Please run `npm run build` in the package directory.');
        }

        $css = file_get_contents($cssPath);

        return new HtmlString("<style>{$css}</style>");
    }

    /**
     * Get the JS for the Coolify dashboard.
     */
    public static function js(): HtmlString
    {
        $jsPath = __DIR__.'/../dist/app.js';

        if (! file_exists($jsPath)) {
            throw new RuntimeException('Unable to load the Coolify dashboard JavaScript. Please run `npm run build` in the package directory.');
        }

        $js = file_get_contents($jsPath);
        $coolify = Js::from(static::scriptVariables());

        return new HtmlString(<<<HTML
            <script type="module">
                window.Coolify = {$coolify};
                {$js}
            </script>
            HTML);
    }
}
