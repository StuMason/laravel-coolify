---
title: Dashboard Overview
description: Coolify dashboard within Laravel
---

## Access

Default route: `/coolify`

Configurable via `COOLIFY_PATH` environment variable.

## Features

The dashboard shows:
- Application status and health
- Recent deployments with status
- Database connection status
- Redis/Dragonfly status
- Quick actions (deploy, restart, view logs)

## Local-Only Access

By default, the dashboard is only accessible when:

```php
app()->environment('local')
```

For production access, configure authentication.
