---
title: coolify:destroy
description: Destroy provisioned infrastructure on Coolify
---

## Usage

```bash
php artisan coolify:destroy
```

## Options

```bash
--force    # Skip confirmation prompts
```

## What It Does

Removes all resources created by `coolify:provision`:

- Application container
- PostgreSQL database
- Dragonfly cache
- Project (if empty)

## Warning

This is a destructive operation. All data in databases will be permanently deleted.

## Examples

Interactive destruction (with confirmations):

```bash
php artisan coolify:destroy
```

Force destruction without prompts:

```bash
php artisan coolify:destroy --force
```

## Cleanup

After destruction, you may want to remove the `.env` entries:

```bash
COOLIFY_PROJECT_UUID=
```
