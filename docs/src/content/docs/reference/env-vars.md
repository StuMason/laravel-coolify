---
title: Environment Variables
description: All supported environment variables
---

## Required

| Variable | Description |
|----------|-------------|
| `COOLIFY_URL` | Coolify instance URL |
| `COOLIFY_TOKEN` | API authentication token |

## Resource UUIDs

Set automatically by `coolify:provision`:

| Variable | Description |
|----------|-------------|
| `COOLIFY_APPLICATION_UUID` | Application identifier |
| `COOLIFY_SERVER_UUID` | Target server |
| `COOLIFY_PROJECT_UUID` | Project container |
| `COOLIFY_ENVIRONMENT` | Environment name |
| `COOLIFY_DATABASE_UUID` | PostgreSQL instance |
| `COOLIFY_REDIS_UUID` | Dragonfly/Redis instance |
| `COOLIFY_DEPLOY_KEY_UUID` | SSH key for git |

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
| `COOLIFY_PHP_VERSION` | `8.4` | PHP version |
| `COOLIFY_HEALTH_CHECK_PATH` | `/up` | Health endpoint |
| `COOLIFY_NGINX_MAX_BODY_SIZE` | `35M` | Nginx body limit |
| `COOLIFY_UPLOAD_MAX_FILESIZE` | `30M` | PHP upload limit |
| `COOLIFY_POST_MAX_SIZE` | `35M` | PHP POST limit |
| `COOLIFY_PHP_MEMORY_LIMIT` | `256M` | PHP memory limit |
| `COOLIFY_PHP_MAX_EXECUTION_TIME` | `60` | PHP timeout |
