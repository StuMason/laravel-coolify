---
title: CI/CD Integration
description: Integrate with GitHub Actions and other CI systems
---

## GitHub Actions

Deploy on push to main:

```yaml
# .github/workflows/deploy.yml
name: Deploy

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'

      - name: Install dependencies
        run: composer install --no-dev --prefer-dist

      - name: Deploy to Coolify
        env:
          COOLIFY_URL: ${{ secrets.COOLIFY_URL }}
          COOLIFY_TOKEN: ${{ secrets.COOLIFY_TOKEN }}
          COOLIFY_APPLICATION_UUID: ${{ secrets.COOLIFY_APPLICATION_UUID }}
        run: php artisan coolify:deploy --force --wait
```

## Required Secrets

Add to GitHub repository settings:

- `COOLIFY_URL` - Your Coolify instance URL
- `COOLIFY_TOKEN` - API token
- `COOLIFY_APPLICATION_UUID` - Application UUID from provision

## Coolify Webhooks

Alternative: Configure Coolify's built-in webhooks for auto-deploy on push.

1. Go to application settings in Coolify
2. Enable "Auto Deploy"
3. Add webhook URL to GitHub repository settings

## Deployment Strategies

### Manual Trigger

```yaml
on:
  workflow_dispatch:
```

### Tag-Based Releases

```yaml
on:
  push:
    tags:
      - 'v*'

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - run: php artisan coolify:deploy --tag=${{ github.ref_name }} --force
```

## Status Checks

Wait for deployment and fail workflow on deployment failure:

```yaml
- name: Deploy and verify
  run: |
    php artisan coolify:deploy --force --wait
    # Exit code reflects deployment status
```
