---
title: coolify:deploy
description: Deploy application on Coolify
---

## Usage

```bash
php artisan coolify:deploy
```

## Options

```bash
--uuid=     # Application UUID (defaults to COOLIFY_APPLICATION_UUID)
--tag=      # Deploy specific git tag
--force     # Skip confirmation
--wait      # Wait for deployment and stream logs
--debug     # Show debug/build logs (enabled with --wait)
```

## Examples

Basic deployment:

```bash
php artisan coolify:deploy
```

Deploy specific tag:

```bash
php artisan coolify:deploy --tag=v1.2.3
```

Deploy and watch logs:

```bash
php artisan coolify:deploy --wait
```

CI/CD usage:

```bash
php artisan coolify:deploy --force --wait
```
