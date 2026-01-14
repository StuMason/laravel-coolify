---
title: CI/CD Integration
description: Integrate with GitHub Actions and other CI systems
---

## Recommended: Coolify Webhooks

The simplest CI/CD approach is to use Coolify's built-in webhooks. When you provision with `coolify:provision`, a webhook is automatically configured.

1. Go to your GitHub repository settings
2. Add the webhook URL shown in the Coolify dashboard
3. Enable "Auto Deploy" in Coolify application settings

Coolify handles deployments automatically on push - no GitHub Actions required.

## GitHub Actions (Optional)

If you need to run tests or other steps before deploying, use the `--uuid` option:

```yaml
# .github/workflows/deploy.yml
name: Deploy

on:
  push:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
      - run: composer install
      - run: php artisan test

  deploy:
    needs: test
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
      - run: composer install --no-dev
      - name: Deploy to Coolify
        env:
          COOLIFY_URL: ${{ secrets.COOLIFY_URL }}
          COOLIFY_TOKEN: ${{ secrets.COOLIFY_TOKEN }}
        run: php artisan coolify:deploy --uuid=${{ secrets.COOLIFY_APP_UUID }} --force --wait
```

## Required Secrets

Add to GitHub repository settings:

- `COOLIFY_URL` - Your Coolify instance URL
- `COOLIFY_TOKEN` - API token
- `COOLIFY_APP_UUID` - Application UUID (shown after provisioning)

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
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
      - run: composer install --no-dev
      - name: Deploy tag
        env:
          COOLIFY_URL: ${{ secrets.COOLIFY_URL }}
          COOLIFY_TOKEN: ${{ secrets.COOLIFY_TOKEN }}
        run: php artisan coolify:deploy --uuid=${{ secrets.COOLIFY_APP_UUID }} --tag=${{ github.ref_name }} --force
```

## Status Checks

Wait for deployment and fail workflow on deployment failure:

```yaml
- name: Deploy and verify
  run: |
    php artisan coolify:deploy --uuid=${{ secrets.COOLIFY_APP_UUID }} --force --wait
    # Exit code reflects deployment status
```
