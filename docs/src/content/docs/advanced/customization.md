---
title: Customization
description: Customize generated Docker configuration
---

## Override Dockerfile

After running `coolify:provision`, modify the generated `Dockerfile` directly. Changes persist across deployments.

## PHP Extensions

Add extensions in Dockerfile base stage:

```dockerfile
FROM php:8.4-fpm-alpine AS base

RUN apk add --no-cache \
    libpng-dev \
    && docker-php-ext-install gd
```

## Nginx Configuration

Edit `docker/nginx.conf`:

```nginx
# Add custom location blocks
location /api {
    # Rate limiting
    limit_req zone=api burst=20 nodelay;
}
```

## PHP Settings

Edit `docker/php.ini`:

```ini
memory_limit=1G
upload_max_filesize=100M
```

## Supervisor Programs

Edit `docker/supervisord.conf` to add custom workers:

```ini
[program:custom-worker]
command=/usr/bin/php /var/www/html/artisan queue:work --queue=custom
autostart=true
autorestart=true
user=www-data
```

## Environment-Specific Config

Use environment variables in configs:

```nginx
# docker/nginx.conf
client_max_body_size ${NGINX_MAX_BODY_SIZE};
```

Set via Coolify environment variables.
