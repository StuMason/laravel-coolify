---
title: coolify:status
description: Check status of Coolify resources
---

## Usage

```bash
php artisan coolify:status
```

## Options

```bash
--uuid=   # Application UUID (defaults to COOLIFY_APPLICATION_UUID)
--all     # Show status of all resources (app, database, redis)
```

## Output

Shows:
- Application status (running, stopped, building)
- Recent deployments
- Database status (if `--all`)
- Redis/Dragonfly status (if `--all`)
