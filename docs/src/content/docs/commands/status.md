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
--uuid=   # Application UUID (defaults to provisioned app)
--all     # Show all applications and databases
```

## Output

Shows:
- Application status (running, stopped, building)
- Recent deployments
- Database status (if `--all`)
- Redis/Dragonfly status (if `--all`)
