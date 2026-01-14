---
title: Introduction
description: Deploy Laravel applications to Coolify with zero-config provisioning
---

Laravel Coolify provisions your entire production infrastructure on [Coolify](https://coolify.io) with a single command.

## What Gets Created

```bash
php artisan coolify:provision
```

This creates:
- Laravel application container (PHP-FPM, Nginx, Horizon, Reverb)
- PostgreSQL database
- Dragonfly cache (Redis-compatible)

All environment variables are configured automatically.

## Available Commands

```bash
coolify:install    # Install package config
coolify:provision  # Create infrastructure
coolify:status     # Show application status
coolify:deploy     # Trigger deployment
coolify:logs       # Stream logs
coolify:restart    # Restart application
coolify:rollback   # Rollback deployment
```

## Requirements

- PHP 8.2+
- Laravel 11/12
- Coolify instance with API access
- Server connected to Coolify

## Quick Start

```bash
composer require stumason/laravel-coolify
php artisan coolify:install
```

Add to `.env`:

```bash
COOLIFY_URL=https://your-coolify.com
COOLIFY_TOKEN=your-api-token
```

Then:

```bash
php artisan coolify:provision
```
