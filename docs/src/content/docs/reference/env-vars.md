---
title: Environment Variables
description: All supported environment variables
---

## Required

| Variable | Description |
|----------|-------------|
| `COOLIFY_URL` | Coolify instance URL |
| `COOLIFY_TOKEN` | API authentication token |

## Optional

| Variable | Default | Description |
|----------|---------|-------------|
| `COOLIFY_TEAM_ID` | null | Team for multi-team setups |
| `COOLIFY_GITHUB_APP_UUID` | null | GitHub App for repo listing |
| `COOLIFY_PATH` | `coolify` | Dashboard URI path |
| `COOLIFY_DOMAIN` | null | Dashboard subdomain |
| `COOLIFY_POLLING_INTERVAL` | `10` | Dashboard refresh (seconds) |
| `COOLIFY_CACHE_TTL` | `30` | API cache duration (seconds) |
| `COOLIFY_TIMEOUT` | `60` | API timeout (seconds) |
| `COOLIFY_NOTIFICATION_EMAIL` | null | Deploy notification email |
| `COOLIFY_LOG_CHANNEL` | `stack` | Log channel for events |

## Docker Configuration

| Variable | Default | Description |
|----------|---------|-------------|
| `COOLIFY_PHP_VERSION` | `8.4` | PHP version (8.3 or 8.4) |
| `COOLIFY_USE_BASE_IMAGE` | `true` | Use pre-built base images for fast builds |
| `COOLIFY_AUTO_MIGRATE` | `true` | Run migrations on container startup |
| `COOLIFY_DB_WAIT_TIMEOUT` | `30` | Seconds to wait for DB before migrating |
| `COOLIFY_HEALTH_CHECK_PATH` | `/up` | Health endpoint |
| `COOLIFY_NGINX_MAX_BODY_SIZE` | `35M` | Nginx body limit |
| `COOLIFY_UPLOAD_MAX_FILESIZE` | `30M` | PHP upload limit |
| `COOLIFY_POST_MAX_SIZE` | `35M` | PHP POST limit |
| `COOLIFY_PHP_MEMORY_LIMIT` | `256M` | PHP memory limit |
| `COOLIFY_PHP_MAX_EXECUTION_TIME` | `60` | PHP timeout |

### Base Image Behavior

When `COOLIFY_USE_BASE_IMAGE=true` (default):
- Uses pre-built images from `ghcr.io/stumason/laravel-coolify-base`
- Build time: ~2-3 minutes
- Automatically selects `-node` variant if `package.json` exists

When `COOLIFY_USE_BASE_IMAGE=false`:
- Builds from `php:x.x-fpm-bookworm` directly
- Build time: ~12 minutes
- Use this if you need custom PHP extensions

## Resource Configuration

Resource UUIDs (application, database, server, etc.) are stored in the database, not environment variables. Run `coolify:provision` to create resources and store their UUIDs automatically.

See [Configuration Reference](/laravel-coolify/reference/config/#database-schema) for the database schema.
