---
title: coolify:install
description: Install package configuration
---

## Usage

```bash
php artisan coolify:install
```

## What It Does

1. Publishes `config/coolify.php`
2. Updates `.env.example` with Coolify variables

## Next Step

Add your Coolify credentials to `.env`:

```bash
COOLIFY_URL=https://your-coolify.com
COOLIFY_TOKEN=your-api-token
```

Then run `coolify:provision` to create infrastructure.
