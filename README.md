# Laravel Coolify

Dashboard and CLI tools for managing Laravel apps deployed on Coolify.

## What this does

- Web dashboard at `/coolify` to view deployments, logs, databases, and environment variables
- Artisan commands to deploy, check status, view logs, restart
- `coolify:provision` command to create an app + Postgres + Redis on Coolify from scratch

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- Coolify instance with API token

## Installation

```bash
composer require stumason/laravel-coolify
php artisan coolify-dashboard:install
```

Add to `.env`:

```
COOLIFY_URL=https://app.coolify.io
COOLIFY_TOKEN=your-api-token
```

The token needs to be a **root-level API token** (created by a Coolify admin under Keys & Tokens > API Tokens). Team tokens may work for basic operations but provisioning requires root access.

## Commands

```bash
coolify:status              # Show app status
coolify:status --all        # Show all apps and databases
coolify:deploy              # Trigger deployment
coolify:deploy --wait       # Wait for deployment to finish
coolify:logs                # View logs
coolify:logs --follow       # Stream logs
coolify:restart             # Restart app
coolify:rollback            # Rollback to previous deployment
coolify:provision           # Create new app + database + redis
coolify:destroy             # Delete a project
```

## Dashboard

Available at `/coolify`. Shows:

- Deployment history and build logs
- Application logs
- Database/Redis status with start/stop controls
- Environment variables (view, add, delete)
- Application settings

Only accessible in `local` environment by default. For production access, add to your `CoolifyServiceProvider`:

```php
Coolify::auth(function ($request) {
    return $request->user()?->email === 'admin@example.com';
});
```

## Provisioning

Creates a new application on Coolify with optional Postgres and Dragonfly (Redis).

```bash
php artisan coolify:provision
```

Uses SSH deploy keys instead of GitHub Apps. GitHub Apps have a shared rate limit (5000 req/hour across all apps) which causes random "Repository not found" errors when you have multiple projects. Deploy keys use SSH directly, no rate limits.

After provisioning, you need to:

1. Add the deploy key to your GitHub repo
2. Set up a webhook for auto-deploy (the command shows you the URL)

## Facade

```php
use Stumason\Coolify\Facades\Coolify;

Coolify::deploy();
Coolify::status();
Coolify::logs();
Coolify::applications()->all();
Coolify::databases()->all();
```