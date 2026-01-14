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

    // Docker configuration
    'docker' => [
        'php_version' => env('COOLIFY_PHP_VERSION', '8.4'),
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

## Resource Configuration

Resource UUIDs (application, database, redis, etc.) are stored in the `coolify_resources` database table, not in config. Run the migration after installing:

```bash
php artisan migrate
```

See [Database Schema](#database-schema) for the table structure.

## Database Schema

The `coolify_resources` table stores provisioned resource information:

| Column | Type | Description |
|--------|------|-------------|
| `name` | string | Unique resource name |
| `server_uuid` | string | Coolify server |
| `project_uuid` | string | Coolify project |
| `environment` | string | Environment (production, staging) |
| `deploy_key_uuid` | string | SSH key for git access |
| `repository` | string | GitHub repository (owner/repo) |
| `branch` | string | Git branch |
| `application_uuid` | string | Coolify application |
| `database_uuid` | string | PostgreSQL instance |
| `redis_uuid` | string | Dragonfly/Redis instance |
| `is_default` | boolean | Default resource for commands |
| `metadata` | json | Additional data |
