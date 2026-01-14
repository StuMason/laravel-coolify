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

## Database Migration

Resource configuration is stored in the database. Run the migration after installing:

```bash
php artisan vendor:publish --tag=coolify-migrations
php artisan migrate
```

This creates the `coolify_resources` table that stores application, database, and server UUIDs after provisioning.

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
