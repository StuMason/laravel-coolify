---
title: Configuration Reference
description: Complete config/coolify.php reference
---

## Full Configuration

```php
<?php

return [
    // Coolify instance URL
    'url' => env('COOLIFY_URL', 'https://app.coolify.io'),

    // API token for authentication
    'token' => env('COOLIFY_TOKEN'),

    // Team ID (for multi-team setups)
    'team_id' => env('COOLIFY_TEAM_ID'),

    // GitHub App UUID (optional, for repo listing)
    'github_app_uuid' => env('COOLIFY_GITHUB_APP_UUID'),

    // Dashboard URI path
    'path' => env('COOLIFY_PATH', 'coolify'),

    // Dashboard domain (null = same as app)
    'domain' => env('COOLIFY_DOMAIN'),

    // Dashboard middleware
    'middleware' => ['web'],

    // Dashboard auto-refresh interval (seconds)
    'polling_interval' => env('COOLIFY_POLLING_INTERVAL', 10),

    // API response cache duration (seconds)
    'cache_ttl' => env('COOLIFY_CACHE_TTL', 30),

    // API request timeout (seconds)
    'timeout' => env('COOLIFY_TIMEOUT', 60),

    // Notification email
    'notifications' => [
        'email' => env('COOLIFY_NOTIFICATION_EMAIL'),
    ],

    // Log channel for Coolify events
    'log_channel' => env('COOLIFY_LOG_CHANNEL', 'stack'),

    // Project UUID (set by coolify:provision)
    'project_uuid' => env('COOLIFY_PROJECT_UUID'),

    // Docker configuration
    'docker' => [
        'php_version' => env('COOLIFY_PHP_VERSION', '8.4'),
        'use_base_image' => env('COOLIFY_USE_BASE_IMAGE', true),
        'auto_migrate' => env('COOLIFY_AUTO_MIGRATE', true),
        'db_wait_timeout' => env('COOLIFY_DB_WAIT_TIMEOUT', 30),
        'health_check_path' => env('COOLIFY_HEALTH_CHECK_PATH', '/up'),
        'nginx' => [
            'client_max_body_size' => env('COOLIFY_NGINX_MAX_BODY_SIZE', '35M'),
            'upload_max_filesize' => env('COOLIFY_UPLOAD_MAX_FILESIZE', '30M'),
            'post_max_size' => env('COOLIFY_POST_MAX_SIZE', '35M'),
        ],
        'php' => [
            'memory_limit' => env('COOLIFY_PHP_MEMORY_LIMIT', '256M'),
            'max_execution_time' => env('COOLIFY_PHP_MAX_EXECUTION_TIME', 60),
        ],
    ],
];
```

## How Application Lookup Works

Only `COOLIFY_PROJECT_UUID` is stored in your `.env` file. All other resource UUIDs are fetched from the Coolify API automatically.

When you run commands like `coolify:deploy` or `coolify:status`:

1. The package reads `COOLIFY_PROJECT_UUID` from your config
2. Fetches all applications from Coolify
3. Matches your local `git remote get-url origin` with application git repositories
4. Uses the matching application for operations

This means no manual UUID configuration is needed after provisioning.
