---
title: coolify:rollback
description: Rollback to previous deployment
---

## Usage

```bash
php artisan coolify:rollback
```

Interactive prompt shows recent deployments to choose from.

## Options

```bash
--uuid=         # Application UUID (defaults to provisioned app)
--deployment=   # Specific deployment UUID to rollback to
--force         # Rollback without confirmation
```

## Examples

Interactive rollback:

```bash
php artisan coolify:rollback
```

Rollback to specific deployment:

```bash
php artisan coolify:rollback --deployment=abc123
```

## How It Works

Rollback redeploys the Docker image from a previous successful deployment. The git commit for that deployment is redeployed.
