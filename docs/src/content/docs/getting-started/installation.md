---
title: Installation
description: Install Laravel Coolify package
---

## Install Package

```bash
composer require stumason/laravel-coolify
```

## Run Installer

```bash
php artisan coolify:install
```

Publishes `config/coolify.php` and generates Dockerfile.

## Run Migration

```bash
php artisan vendor:publish --tag=coolify-migrations
php artisan migrate
```

Creates the `coolify_resources` table for storing provisioned resource configuration.

## Get API Token

1. Log in to Coolify
2. Go to **Settings > API Tokens**
3. Create new token
4. Copy the token

## Configure

Add to `.env`:

```bash
COOLIFY_URL=https://your-coolify.com
COOLIFY_TOKEN=your-api-token
```

Optional:

```bash
COOLIFY_TEAM_ID=     # If multiple teams
COOLIFY_TIMEOUT=60   # Request timeout seconds
COOLIFY_CACHE_TTL=30 # Cache duration seconds
```

## Verify

```bash
php artisan coolify:status --all
```
