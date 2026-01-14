---
title: Configuration
description: Configure Laravel Coolify
---

## Environment Variables

Add to your `.env`:

```bash
COOLIFY_URL=https://your-coolify.com  # Coolify instance URL
COOLIFY_TOKEN=your-api-token          # API token
```

Optional:

```bash
COOLIFY_TEAM_ID=            # Team ID (if multiple teams)
COOLIFY_PATH=coolify        # Dashboard URI path
COOLIFY_TIMEOUT=60          # API request timeout
COOLIFY_CACHE_TTL=30        # API cache duration
COOLIFY_POLLING_INTERVAL=10 # Dashboard refresh interval
```

Docker config:

```bash
COOLIFY_PHP_VERSION=8.4
COOLIFY_HEALTH_CHECK_PATH=/up
COOLIFY_NGINX_MAX_BODY_SIZE=35M
COOLIFY_PHP_MEMORY_LIMIT=256M
```

## Database Storage

Resource configuration (UUIDs for applications, databases, etc.) is stored in the `coolify_resources` database table. This is created automatically when you run `php artisan coolify:install`.

## Dashboard Authentication

Default: local access only.

For production:

```php
// app/Providers/AppServiceProvider.php
use Stumason\Coolify\Coolify;

public function boot(): void
{
    Coolify::auth(function ($request) {
        return $request->user()?->isAdmin();
    });
}
```
