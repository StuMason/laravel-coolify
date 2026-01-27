---
title: Dashboard Overview
description: A beautiful, feature-rich dashboard for managing your Coolify infrastructure
---

The Laravel Coolify dashboard gives you complete control over your infrastructure from within your Laravel application. Think of it like Laravel Horizon, but for your entire deployment pipeline.

![Dashboard Screenshot](/dashboard.png)

## Access

Default route: `/coolify`

Configurable via `COOLIFY_PATH` environment variable or in `config/coolify.php`.

## Features

### Hero Status Card

The main dashboard shows your application's current state at a glance:

- **Animated status indicator** - Pulsing green dot when running, red when stopped
- **Build pack info** - Shows if using Dockerfile, Nixpacks, etc.
- **Server info** - Which Coolify server hosts your app
- **Quick links** - Direct links to your site, GitHub repo, and Coolify UI

### Deployment Info Bar

Essential deployment information always visible:

| Field | Description |
|-------|-------------|
| **Branch** | Current Git branch with link to GitHub |
| **Current Commit** | Short SHA with GitHub link and copy button |
| **Last Deploy** | Relative time since last deployment with duration |
| **Project** | Coolify project and environment name |

### One-Click Deployments

The deploy button dropdown offers multiple options:

- **Deploy Latest** - Deploy HEAD from your current branch
- **Force Rebuild** - Full rebuild without Docker cache
- **Deploy Specific Commit** - Enter any commit SHA to deploy
- **Redeploy Previous** - Quick redeploy of recent commits

### Database & Cache Cards

Resource cards for your databases showing:

- Status badge (Healthy/Stopped/etc.)
- Database type and image version
- Internal hostname for container networking
- Public port if exposed
- Start/Stop/Restart controls
- Direct link to Coolify UI

### Recent Deployments

Full deployment history with:

- **Status icons** - Visual indicators for finished/failed/in-progress
- **Commit info** - SHA with GitHub links, commit messages
- **Duration** - How long each deployment took
- **Relative timestamps** - "26m ago", "2h ago", etc.
- **Redeploy button** - One-click redeploy of any commit
- **Inline logs** - Expandable build logs without leaving the page

### Accordion Build Logs

Click "Logs" on any deployment to expand inline logs:

- **Lazy loading** - Logs fetched on demand, not upfront
- **6-line preview** - See a summary without overwhelming detail
- **"See more" expansion** - Expand to see all logs
- **Color-coded output** - stdout vs stderr differentiated
- **Line numbers** - Easy reference for debugging
- **Link to full logs** - Jump to dedicated log viewer

## Pages

The dashboard includes multiple pages accessible from the sidebar:

| Page | Description |
|------|-------------|
| **Dashboard** | Main overview with status, deployments, resources |
| **Deployments** | Full deployment history with search and filters |
| **Resources** | All databases and services in your environment |
| **Configuration** | Environment variables, settings, backup schedules |
| **Logs** | Real-time application logs |
| **Kick** | Laravel Kick introspection (when configured) |

### Kick Tab

When your deployed application has [Laravel Kick](https://github.com/StuMason/laravel-kick) installed and configured, a **Kick** tab appears with:

- Health checks (database, cache, storage, redis)
- System stats (CPU, memory, disk, uptime)
- Log viewer with filtering and search
- Queue status and failed jobs
- Artisan command execution

See [Kick Integration](/dashboard/kick) for setup details.

## Quick Actions

The command palette (`Cmd/Ctrl + K`) provides quick access to:

- Deploy application
- Restart application
- View logs
- Navigate between pages
- Stop/Start services

## Local-Only Access

By default, the dashboard is only accessible in local environment:

```php
app()->environment('local')
```

For production access, see [Authentication](/dashboard/authentication).
