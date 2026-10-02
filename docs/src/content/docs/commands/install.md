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
3. Adds `$middleware->trustProxies(at: '*')` to `bootstrap/app.php` if it isn't already there

:::note
Trusting every proxy is correct when the container is only reachable through Coolify's Traefik proxy, which is how Coolify runs it: Laravel needs the `X-Forwarded-*` headers to see HTTPS and the real client IP. If you also expose the container's port directly, anyone can send those headers and spoof their IP or scheme. In that case narrow `at:` to the proxy's address.
:::

## Next Step

Add your Coolify credentials to `.env`:

```bash
COOLIFY_URL=https://your-coolify.com
COOLIFY_TOKEN=your-api-token
```

Then run `coolify:provision` to create infrastructure.
