<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Coolify API URL
    |--------------------------------------------------------------------------
    |
    | This is the base URL of your Coolify instance. This should be the root
    | URL where your Coolify dashboard is accessible, without a trailing
    | slash. For example: https://coolify.example.com
    |
    */

    'url' => env('COOLIFY_URL', 'https://app.coolify.io'),

    /*
    |--------------------------------------------------------------------------
    | Coolify API Token
    |--------------------------------------------------------------------------
    |
    | Your Coolify API token for authentication. Generate this from your
    | Coolify dashboard under Settings > API Tokens. Keep this secret
    | and never commit it to version control.
    |
    */

    'token' => env('COOLIFY_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Coolify Team ID
    |--------------------------------------------------------------------------
    |
    | The team ID to use for API requests. If you have multiple teams in
    | Coolify, specify which one this application belongs to. Leave
    | null to use the default team.
    |
    */

    'team_id' => env('COOLIFY_TEAM_ID'),

    /*
    |--------------------------------------------------------------------------
    | Application UUID
    |--------------------------------------------------------------------------
    |
    | The UUID of this application in Coolify. This links your Laravel app
    | to its corresponding Coolify application for deployments, logs,
    | and monitoring. Find this in your Coolify application settings.
    |
    */

    'application_uuid' => env('COOLIFY_APPLICATION_UUID'),

    /*
    |--------------------------------------------------------------------------
    | Server UUID
    |--------------------------------------------------------------------------
    |
    | The UUID of the server where this application is deployed. Used for
    | server-level monitoring and resource management. Find this in your
    | Coolify server settings.
    |
    */

    'server_uuid' => env('COOLIFY_SERVER_UUID'),

    /*
    |--------------------------------------------------------------------------
    | Project UUID
    |--------------------------------------------------------------------------
    |
    | The UUID of the project this application belongs to in Coolify.
    | Projects are containers for grouping related applications,
    | databases, and services together.
    |
    */

    'project_uuid' => env('COOLIFY_PROJECT_UUID'),

    /*
    |--------------------------------------------------------------------------
    | Environment Name
    |--------------------------------------------------------------------------
    |
    | The environment name within your Coolify project. Common values are
    | 'production', 'staging', or 'development'. This determines which
    | environment's resources are managed.
    |
    */

    'environment' => env('COOLIFY_ENVIRONMENT', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Deploy Key UUID
    |--------------------------------------------------------------------------
    |
    | The UUID of the SSH key to use for cloning private repositories.
    | Deploy keys are preferred over GitHub Apps because they don't hit
    | GitHub API rate limits - they use SSH directly. Find available keys
    | in Coolify under Security -> Private Keys.
    |
    | After provisioning, you'll need to add the public key to your
    | GitHub repository as a deploy key (Settings -> Deploy Keys).
    |
    */

    'deploy_key_uuid' => env('COOLIFY_DEPLOY_KEY_UUID'),

    /*
    |--------------------------------------------------------------------------
    | GitHub App UUID (Legacy/Optional)
    |--------------------------------------------------------------------------
    |
    | The UUID of a GitHub App for listing repositories during provisioning.
    | This is OPTIONAL - if not set, you can enter the repository manually.
    | GitHub Apps are subject to API rate limits (5000 req/hour shared
    | across all Coolify apps using that GitHub App).
    |
    | For auto-deploy on push, use a manual webhook instead of GitHub App.
    | See the dashboard's Webhook Setup section after provisioning.
    |
    */

    'github_app_uuid' => env('COOLIFY_GITHUB_APP_UUID'),

    /*
    |--------------------------------------------------------------------------
    | Dashboard Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where the Coolify dashboard will be accessible
    | from. Feel free to change this path to anything you like. Note
    | that this doesn't affect the internal API routes.
    |
    */

    'path' => env('COOLIFY_PATH', 'coolify'),

    /*
    |--------------------------------------------------------------------------
    | Dashboard Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain where the Coolify dashboard will be accessible
    | from. If this setting is null, the dashboard will be accessible
    | under the same domain as the application.
    |
    */

    'domain' => env('COOLIFY_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Dashboard Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will be attached to every route in the Coolify
    | dashboard, giving you the chance to add your own middleware
    | to this list or modify the existing middleware.
    |
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Resource UUIDs
    |--------------------------------------------------------------------------
    |
    | UUIDs for associated Coolify resources like databases and services.
    | These enable direct management and monitoring of your app's
    | infrastructure dependencies from within Laravel.
    |
    */

    'resources' => [
        'database' => env('COOLIFY_DATABASE_UUID'),
        'redis' => env('COOLIFY_REDIS_UUID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Polling Interval
    |--------------------------------------------------------------------------
    |
    | The interval in seconds between automatic status checks when
    | viewing the dashboard. Set to 0 to disable auto-refresh.
    | Recommended: 5-30 seconds.
    |
    */

    'polling_interval' => env('COOLIFY_POLLING_INTERVAL', 10),

    /*
    |--------------------------------------------------------------------------
    | Cache TTL
    |--------------------------------------------------------------------------
    |
    | How long to cache API responses in seconds. This reduces the number
    | of API calls to your Coolify instance. Set to 0 to disable
    | caching entirely.
    |
    */

    'cache_ttl' => env('COOLIFY_CACHE_TTL', 30),

    /*
    |--------------------------------------------------------------------------
    | API Timeout
    |--------------------------------------------------------------------------
    |
    | The default timeout in seconds for API requests to Coolify. Some
    | operations like creating applications can take longer, so this
    | can be overridden per-request. Default: 60 seconds.
    |
    */

    'timeout' => env('COOLIFY_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Configure notification channels for deployment events and alerts.
    | Email notifications for deployment success, failure, and health alerts.
    |
    */

    'notifications' => [
        'email' => env('COOLIFY_NOTIFICATION_EMAIL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channel
    |--------------------------------------------------------------------------
    |
    | The log channel to use for Coolify deployment events. This allows
    | you to separate Coolify-related logs from your application logs.
    | Set to 'stack' to use your default logging configuration.
    |
    */

    'log_channel' => env('COOLIFY_LOG_CHANNEL', 'stack'),

];
