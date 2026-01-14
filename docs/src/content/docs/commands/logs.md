---
title: coolify:logs
description: View application logs from Coolify
---

## Usage

```bash
php artisan coolify:logs
```

## Options

```bash
--uuid=         # Application UUID (defaults to COOLIFY_APPLICATION_UUID)
--deployment=   # Show logs for specific deployment
--lines=100     # Number of lines to retrieve
--follow        # Continuously poll for new logs
--debug         # Show debug/build logs (hidden by default)
```

## Examples

View recent logs:

```bash
php artisan coolify:logs
```

Follow logs in real-time:

```bash
php artisan coolify:logs --follow
```

View deployment build logs:

```bash
php artisan coolify:logs --deployment=abc123 --debug
```

Get more history:

```bash
php artisan coolify:logs --lines=500
```
