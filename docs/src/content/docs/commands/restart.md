---
title: coolify:restart
description: Restart application on Coolify
---

## Usage

```bash
php artisan coolify:restart
```

## Options

```bash
--uuid=   # Application UUID (defaults to COOLIFY_APPLICATION_UUID)
--force   # Restart without confirmation
```

## When to Use

- After updating environment variables in Coolify
- To clear in-memory state
- After configuration changes that require a fresh container

Note: For code changes, use `coolify:deploy` instead.
