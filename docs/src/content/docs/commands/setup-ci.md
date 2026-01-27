---
title: coolify:setup-ci
description: Generate GitHub Actions workflow for CI/CD deployments
---

## Usage

```bash
php artisan coolify:setup-ci
```

## Options

```bash
--branch=     # Branch to deploy on push (default: main)
--no-manual   # Disable manual workflow_dispatch trigger
--force       # Overwrite existing workflow file
```

## What It Does

Generates `.github/workflows/coolify-deploy.yml` with:

- Automatic deployment on push to specified branch
- Manual trigger via GitHub Actions UI
- Proper secrets configuration

## Examples

Basic setup:

```bash
php artisan coolify:setup-ci
```

Deploy on push to `production` branch:

```bash
php artisan coolify:setup-ci --branch=production
```

Overwrite existing workflow:

```bash
php artisan coolify:setup-ci --force
```

## Generated Workflow

```yaml
name: Deploy to Coolify

on:
  push:
    branches: [main]
  workflow_dispatch:

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
      - run: composer install --no-dev --optimize-autoloader
      - run: php artisan coolify:deploy --force --wait
        env:
          COOLIFY_URL: ${{ secrets.COOLIFY_URL }}
          COOLIFY_TOKEN: ${{ secrets.COOLIFY_TOKEN }}
          COOLIFY_PROJECT_UUID: ${{ secrets.COOLIFY_PROJECT_UUID }}
```

## Required Secrets

Add these to your GitHub repository settings:

| Secret | Description |
|--------|-------------|
| `COOLIFY_URL` | Your Coolify instance URL |
| `COOLIFY_TOKEN` | API token from Coolify |
| `COOLIFY_PROJECT_UUID` | Project UUID (from `coolify:provision`) |
