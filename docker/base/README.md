# Laravel Coolify Base Images

Pre-built Docker images with all system dependencies and PHP extensions compiled. Using these images reduces deployment time from **~12 minutes to ~2-3 minutes**.

## Available Images

| Image | PHP | Node.js | Use Case |
|-------|-----|---------|----------|
| `ghcr.io/stumason/laravel-coolify-base:8.3` | 8.3 | - | API-only or Blade apps |
| `ghcr.io/stumason/laravel-coolify-base:8.4` | 8.4 | - | API-only or Blade apps |
| `ghcr.io/stumason/laravel-coolify-base:8.3-node` | 8.3 | 20 LTS | Full-stack with Vite/Inertia |
| `ghcr.io/stumason/laravel-coolify-base:8.4-node` | 8.4 | 20 LTS | Full-stack with Vite/Inertia |

## What's Included

### System Dependencies
- nginx (web server)
- supervisor (process manager)
- curl, wget, zip, unzip, git
- All libraries needed for PHP extensions

### PHP Extensions
- **Database:** pdo, pdo_mysql, pdo_pgsql, pgsql
- **Core:** mbstring, xml, bcmath, intl, opcache, pcntl, zip
- **Media:** gd (with freetype & jpeg support)
- **Cache:** redis (via PECL)

### Node.js (for `-node` variants)
- Node.js 20 LTS
- npm (latest)

## What's NOT Included

These are intentionally excluded as they're application-specific:

- **Chromium/Browsershot** - Add to your Dockerfile if needed
- **Application code** - Copied during your deployment
- **Custom PHP config** - Applied via `docker/php.ini`
- **Nginx config** - Applied via `docker/nginx.conf`

## How It Works

When `coolify.docker.use_base_image` is `true` (default), the generated Dockerfile uses these base images:

```dockerfile
# Instead of this (slow):
FROM php:8.4-fpm-bookworm
RUN apt-get update && apt-get install -y ...  # 2-3 min
RUN docker-php-ext-install ...                 # 2-3 min
RUN pecl install redis ...                     # 1 min

# We use this (fast):
FROM ghcr.io/stumason/laravel-coolify-base:8.4-node
# Everything already installed!
```

## Building Locally

To build locally for testing:

```bash
# PHP 8.4
docker build -f docker/base/Dockerfile.8.4 -t laravel-coolify-base:8.4 docker/base/

# PHP 8.4 with Node
docker build -f docker/base/Dockerfile.8.4-node -t laravel-coolify-base:8.4-node docker/base/
```

## Opting Out

If you need to customize the base image or use a different PHP configuration:

```env
COOLIFY_USE_BASE_IMAGE=false
```

This will generate a Dockerfile that builds from `php:x.x-fpm-bookworm` directly.

## Security

Base images are rebuilt **nightly** via GitHub Actions to include the latest security patches. Each build:

1. Pulls the latest `php:x.x-fpm-bookworm` image
2. Rebuilds with updated system packages
3. Pushes to GHCR with the same tag

This ensures your deployments always use patched dependencies without any action required.
