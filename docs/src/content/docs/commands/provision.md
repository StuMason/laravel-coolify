---
title: coolify:provision
description: Provision complete Laravel stack on Coolify
---

## Usage

```bash
php artisan coolify:provision
```

Interactive prompts guide server, project, and repository selection.

## Options

```bash
--name=             # Application name
--domain=           # Application domain
--server=           # Server UUID
--project=          # Project UUID
--environment=      # Environment (default: production)
--repository=       # GitHub repository (owner/repo)
--branch=           # Git branch
--with-postgres     # Create PostgreSQL database
--with-dragonfly    # Create Dragonfly instance
--with-redis        # Create Redis instance
--all               # Create app with Postgres and Dragonfly
--deploy            # Trigger deployment after provisioning
--force             # Skip confirmations
```

## Non-Interactive

```bash
php artisan coolify:provision \
  --server=abc123 \
  --name="My App" \
  --repository=owner/repo \
  --branch=main \
  --all \
  --deploy \
  --force
```

## What Gets Created

1. Project (if needed)
2. Environment within project
3. Application with Dockerfile configuration
4. PostgreSQL database (with `--with-postgres` or `--all`)
5. Dragonfly cache (with `--with-dragonfly` or `--all`)
6. Environment variables linking all services

## Configuration Storage

After provisioning, `COOLIFY_PROJECT_UUID` is automatically added to your local `.env` file:

```bash
COOLIFY_PROJECT_UUID=abc123-def456-...
```

All other commands (`coolify:deploy`, `coolify:status`, etc.) automatically find your application by matching your local git repository with applications in Coolify. No manual UUID configuration is required.

## Generated Files

Creates in your project:

```
Dockerfile
docker/
├── nginx.conf
├── php.ini
└── supervisord.conf
```
