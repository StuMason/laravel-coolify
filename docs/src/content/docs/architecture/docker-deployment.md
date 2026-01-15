---
title: Docker Deployment
description: How Laravel Coolify generates optimized Dockerfiles for your Laravel application
---

Laravel Coolify generates production-ready Docker configurations for your Laravel application. This page explains how it works and how to customize it.

## Quick Start

Run the install command to generate Docker files:

```bash
php artisan coolify:install
```

This creates:
- `Dockerfile` - Multi-stage build for your application
- `docker/supervisord.conf` - Process manager configuration
- `docker/nginx.conf` - Web server configuration
- `docker/php.ini` - PHP runtime settings
- `docker/entrypoint.sh` - Startup script for migrations and optimization

## Pre-built Base Images

By default, Laravel Coolify uses pre-built base images from GitHub Container Registry. These images have all system dependencies and PHP extensions pre-compiled, reducing build time from **~12 minutes to ~2-3 minutes**.

### Available Images

| Image | PHP | Node.js | Use Case |
|-------|-----|---------|----------|
| `ghcr.io/stumason/laravel-coolify-base:8.3` | 8.3 | - | API-only or Blade apps |
| `ghcr.io/stumason/laravel-coolify-base:8.4` | 8.4 | - | API-only or Blade apps |
| `ghcr.io/stumason/laravel-coolify-base:8.3-node` | 8.3 | 20 LTS | Full-stack with Vite/Inertia |
| `ghcr.io/stumason/laravel-coolify-base:8.4-node` | 8.4 | 20 LTS | Full-stack with Vite/Inertia |

### Automatic Detection

The generator automatically selects the right image variant:
- If `package.json` exists → uses `-node` variant
- Otherwise → uses standard variant

### What's Included in Base Images

**System Dependencies:**
- nginx, supervisor, curl, wget, zip, unzip, git

**PHP Extensions:**
- Database: pdo, pdo_mysql, pdo_pgsql, pgsql
- Core: mbstring, xml, bcmath, intl, opcache, pcntl, zip
- Media: gd (with freetype & jpeg)
- Cache: redis (via PECL)

**What's NOT Included:**
- Chromium/Browsershot (add to your Dockerfile if needed)
- Application code (copied during deployment)

### Opting Out of Base Images

If you need custom PHP extensions or want full control:

```env
COOLIFY_USE_BASE_IMAGE=false
```

This generates a Dockerfile that builds from `php:x.x-fpm-bookworm` directly.

## Container Startup

When your container starts, the entrypoint script runs automatically:

### 1. Database Connection Check

The script waits for your database to be available before proceeding:

```
[1/3] Waiting for database connection...
       Waiting for database... (1/30s)
       Database connected!
```

Configure the timeout:
```env
COOLIFY_DB_WAIT_TIMEOUT=30  # seconds (default)
```

### 2. Database Migrations

Migrations run automatically with `--force` flag:

```
[1/3] Running database migrations...
       Migrations completed successfully.
```

**If migrations fail, the container exits with an error** - this prevents your app from starting with an inconsistent database state.

To disable automatic migrations:
```env
COOLIFY_AUTO_MIGRATE=false
```

### 3. Application Optimization

Laravel's `optimize` command caches config, routes, views, and events:

```
[2/3] Optimizing application...
       Optimization completed (config, routes, views, events cached).
```

### 4. Storage Link

Ensures the storage symlink exists:

```
[3/3] Ensuring storage link...
       Storage link ready.
```

## Handling Migration Failures

Since migrations run on container startup, you need a strategy for failures:

### During Development

1. Fix the migration locally
2. Push the fix
3. Redeploy

### Rollback Scenario

If you deploy a broken migration:

1. The container will fail to start (expected behavior)
2. Create a new migration to fix the issue
3. Deploy the fix
4. The new container will run both migrations

:::note
Migrations run as `root` user during container startup. The application itself runs as `www-data` via php-fpm.
:::

## Auto-detected Workers

The Dockerfile generator detects installed Laravel packages and configures supervisor workers:

| Package | Worker Added |
|---------|--------------|
| Laravel Horizon | `horizon` process |
| Laravel Reverb | `reverb:start` WebSocket server |
| Scheduler | `schedule:run` loop |

## Customization

### PHP Version

```env
COOLIFY_PHP_VERSION=8.4  # or 8.3
```

### Health Check

```env
COOLIFY_HEALTH_CHECK_PATH=/up  # default Laravel health endpoint
```

### PHP Settings

```env
COOLIFY_PHP_MEMORY_LIMIT=256M
COOLIFY_PHP_MAX_EXECUTION_TIME=60
```

### Upload Limits

```env
COOLIFY_NGINX_MAX_BODY_SIZE=35M
COOLIFY_UPLOAD_MAX_FILESIZE=30M
COOLIFY_POST_MAX_SIZE=35M
```

## Regenerating Docker Files

To regenerate after configuration changes:

```bash
php artisan coolify:install --force
```

Then commit and deploy!

## Security

Base images are rebuilt **nightly** via GitHub Actions to include the latest security patches. This ensures your deployments always use patched dependencies.
