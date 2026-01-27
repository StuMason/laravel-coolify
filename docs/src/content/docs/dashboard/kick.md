---
title: Laravel Kick Integration
description: Enhanced application introspection with Laravel Kick
---

The dashboard integrates with [Laravel Kick](https://github.com/StuMason/laravel-kick) to provide deep introspection into your running applications.

## What is Laravel Kick?

Laravel Kick is a package you install on your **deployed application** that exposes secure endpoints for:

- Health checks (database, cache, storage, redis)
- System stats (CPU, memory, disk, uptime)
- Log file viewing with filtering
- Queue status and failed jobs
- Artisan command execution

## Setup

### 1. Install Kick on Your Deployed App

```bash
composer require stumason/laravel-kick
```

### 2. Configure Environment Variables

Add to your application's Coolify environment variables:

```bash
KICK_ENABLED=true
KICK_TOKEN=your-secure-random-token
```

Generate a secure token:

```bash
openssl rand -base64 32
```

### 3. Deploy

After deployment, a **Kick** tab appears in the dashboard for that application.

## Dashboard Features

### Overview Tab

- **Health Checks** - Real-time status of database, cache, storage, and redis connections
- **System Stats** - CPU load, memory usage, disk space, and uptime
- **Queue Status** - Quick view of pending jobs and failed count

### Logs Tab

- **File Selection** - Browse all Laravel log files
- **Level Filtering** - Filter by DEBUG, INFO, WARNING, ERROR, etc.
- **Search** - Full-text search across log entries
- **Line Limits** - Control how many lines to display

### Queue Tab

- **Connection Info** - Current queue driver and status
- **Queue Sizes** - Pending jobs per queue (default, high, low)
- **Failed Jobs** - List of failed jobs with exception details

### Artisan Tab

- **Command List** - All whitelisted artisan commands
- **Execution** - Run commands directly from the dashboard
- **Output** - View command output and exit codes

## Security

### Token Authentication

All Kick endpoints require the `KICK_TOKEN` for authentication. The token is:

- Sent as a Bearer token in the Authorization header
- Never exposed in the dashboard UI
- Required for every request

### Command Whitelisting

Artisan commands must be explicitly whitelisted in your Kick configuration. By default, only safe read-only commands are allowed.

### Rate Limiting

The artisan execution endpoint is rate-limited to 10 requests per minute to prevent abuse.

## Configuration

In your **Laravel Coolify** config (`config/coolify.php`):

```php
'kick' => [
    // Enable/disable kick integration globally
    'enabled' => env('COOLIFY_KICK_ENABLED', true),

    // Cache TTL for kick config lookups (seconds)
    'cache_ttl' => env('COOLIFY_KICK_CACHE_TTL', 60),

    // Timeout for kick API requests (seconds)
    'timeout' => env('COOLIFY_KICK_TIMEOUT', 10),
],
```

In your **deployed app's** Coolify environment:

```bash
KICK_ENABLED=true
KICK_TOKEN=your-secure-token
KICK_PREFIX=kick              # Optional, defaults to 'kick'
```

## Troubleshooting

### "Kick Service Unavailable" Error

This usually means:

1. **Route cache needs rebuilding** - Run `php artisan route:cache` on the server
2. **Application is restarting** - Wait for deployment to complete
3. **Kick package not installed** - Verify with `composer show stumason/laravel-kick`

### Tab Not Appearing

The Kick tab only appears when:

1. `KICK_ENABLED=true` is set in the app's environment
2. `KICK_TOKEN` is configured
3. The Kick endpoints are reachable

### Authentication Errors

Verify your `KICK_TOKEN` matches between:

- The app's Coolify environment variables
- What Laravel Kick expects on the deployed app
