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

After running `coolify:provision`, this is added automatically:

```bash
COOLIFY_PROJECT_UUID=your-project-uuid  # Set by coolify:provision
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

## How Application Lookup Works

The dashboard fetches your application directly from the Coolify API using your project and environment:

1. It reads `COOLIFY_PROJECT_UUID` from your `.env`
2. Fetches resources from the selected environment (defaults to "production")
3. Uses the first application in that environment

The dashboard includes an environment switcher to view different environments within your project. No manual UUID configuration is needed after provisioning.

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
