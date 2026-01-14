---
title: Configuration
description: Configure Laravel Coolify
---

## Environment Variables

Core:

```bash
COOLIFY_URL=https://your-coolify.com  # Coolify instance URL
COOLIFY_TOKEN=your-api-token          # API token
```

Resource UUIDs (set by coolify:provision):

```bash
COOLIFY_APPLICATION_UUID=   # Your app in Coolify
COOLIFY_SERVER_UUID=        # Target server
COOLIFY_PROJECT_UUID=       # Project container
COOLIFY_DATABASE_UUID=      # PostgreSQL instance
COOLIFY_REDIS_UUID=         # Dragonfly instance
COOLIFY_DEPLOY_KEY_UUID=    # SSH key for git
```

Optional:

```bash
COOLIFY_TEAM_ID=            # Team ID (if multiple teams)
COOLIFY_ENVIRONMENT=production
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
