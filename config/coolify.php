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
    | Project UUID
    |--------------------------------------------------------------------------
    |
    | The UUID of your Coolify project. This is the primary identifier used
    | to discover all resources (applications, databases, services) within
    | your project. Set this to enable automatic resource discovery.
    |
    | To find your project UUID:
    | 1. Go to your Coolify dashboard
    | 2. Navigate to your project
    | 3. The UUID is in the URL: /projects/{uuid}
    |
    */

    'project_uuid' => env('COOLIFY_PROJECT_UUID'),

    /*
    |--------------------------------------------------------------------------
    | Environment Name
    |--------------------------------------------------------------------------
    |
    | The name of the environment within your Coolify project. This is used
    | to filter resources when discovering applications and databases.
    | Common values: 'production', 'staging', 'development'
    |
    */

    'environment' => env('COOLIFY_ENVIRONMENT', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Server UUID
    |--------------------------------------------------------------------------
    |
    | The UUID of the Coolify server. If not set, it will be auto-discovered
    | from your project's applications. Setting this explicitly can speed
    | up provisioning operations.
    |
    */

    'server_uuid' => env('COOLIFY_SERVER_UUID'),

    /*
    |--------------------------------------------------------------------------
    | Application UUID
    |--------------------------------------------------------------------------
    |
    | The UUID of your primary application in Coolify. If not set, it will
    | be auto-discovered as the first application in your environment.
    | This is used by deploy, status, logs, and restart commands.
    |
    */

    'application_uuid' => env('COOLIFY_APPLICATION_UUID'),

    /*
    |--------------------------------------------------------------------------
    | Database UUID
    |--------------------------------------------------------------------------
    |
    | The UUID of your primary database in Coolify. If not set, it will
    | be auto-discovered from your environment (prefers PostgreSQL/MySQL).
    |
    */

    'database_uuid' => env('COOLIFY_DATABASE_UUID'),

    /*
    |--------------------------------------------------------------------------
    | Redis UUID
    |--------------------------------------------------------------------------
    |
    | The UUID of your Redis/Dragonfly instance in Coolify. If not set,
    | it will be auto-discovered from your environment.
    |
    */

    'redis_uuid' => env('COOLIFY_REDIS_UUID'),

    /*
    |--------------------------------------------------------------------------
    | GitHub App UUID (Optional)
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

    /*
    |--------------------------------------------------------------------------
    | Docker Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for the Dockerfile generator. These settings
    | control PHP version, nginx settings, and PHP runtime tuning.
    | The generator creates a production-ready multi-stage Dockerfile with
    | supervisor managing php-fpm, nginx, and detected workers.
    |
    */

    'docker' => [
        // PHP version for the production image (e.g., '8.3', '8.4')
        'php_version' => env('COOLIFY_PHP_VERSION', '8.4'),

        // Health check endpoint path
        'health_check_path' => env('COOLIFY_HEALTH_CHECK_PATH', '/up'),

        // Use pre-built base images for faster deployments (~12 min -> ~2-3 min)
        // Set to false to build from scratch (needed for custom PHP extensions)
        'use_base_image' => env('COOLIFY_USE_BASE_IMAGE', true),

        // Automatically run migrations on container startup
        // Set to false if you want to manage migrations manually
        'auto_migrate' => env('COOLIFY_AUTO_MIGRATE', true),

        // Seconds to wait for database connection before running migrations
        // Increase if your database container starts slowly
        'db_wait_timeout' => env('COOLIFY_DB_WAIT_TIMEOUT', 30),

        // Nginx configuration
        'nginx' => [
            'client_max_body_size' => env('COOLIFY_NGINX_MAX_BODY_SIZE', '35M'),
            'upload_max_filesize' => env('COOLIFY_UPLOAD_MAX_FILESIZE', '30M'),
            'post_max_size' => env('COOLIFY_POST_MAX_SIZE', '35M'),
        ],

        // PHP runtime configuration
        'php' => [
            'memory_limit' => env('COOLIFY_PHP_MEMORY_LIMIT', '256M'),
            'max_execution_time' => env('COOLIFY_PHP_MAX_EXECUTION_TIME', 60),
        ],
    ],

];
