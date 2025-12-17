# Laravel Coolify

Manage, deploy, and monitor your Laravel application on Coolify - like Horizon for your infrastructure.

## Features

- **Dashboard** - Monitor your application, databases, and deployments at `/coolify`
- **Artisan Commands** - Deploy, restart, and check status from the CLI
- **Provisioning** - Create your entire stack (app + Postgres + Dragonfly) in one command
- **API Client** - Programmatic access to all Coolify resources

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- A Coolify instance with API access

## Installation

```bash
composer require stumason/laravel-coolify
```

Publish the configuration and service provider:

```bash
php artisan coolify:install
```

Add your Coolify credentials to `.env`:

```bash
COOLIFY_URL=https://app.coolify.io  # Or your self-hosted URL
COOLIFY_TOKEN=your-api-token
COOLIFY_APPLICATION_UUID=your-app-uuid
```

### API Token Requirements

**Important:** The Coolify API token must have appropriate permissions:

- For **provisioning** (`coolify:provision`), your token needs access to servers and projects, which requires a **root-level API token** created by a Coolify admin
- For **basic operations** (deploy, logs, status), a team-level token is sufficient

To create a root API token:

1. Log into Coolify as an admin
2. Go to **Keys & Tokens** > **API Tokens**
3. Create a token with root/admin access

### Deploy Keys vs GitHub Apps

This package uses **SSH deploy keys** instead of GitHub Apps for private repository access. Here's why:

| | Deploy Keys (SSH) | GitHub Apps |
|---|---|---|
| **Rate Limits** | None (uses SSH) | 5000 req/hour shared across ALL apps |
| **Setup** | Manual (add public key to repo) | Automatic |
| **Auto-Deploy** | Manual webhook setup | Automatic via GitHub App |
| **Reliability** | Very reliable | Can fail when rate limited |

**Why this matters:** GitHub Apps share their 5000 request/hour rate limit across ALL Coolify applications using that app. If you have multiple projects deploying frequently, you'll hit rate limits and deployments will fail with "Repository not found" errors.

Deploy keys use SSH directly - no GitHub API calls, no rate limits, no mysterious failures.

**Trade-off:** You need to set up webhooks manually for auto-deploy on push. The provisioning command shows you exactly how to do this.

## Quick Start

### Check Status

```bash
php artisan coolify:status
```

### Deploy

```bash
php artisan coolify:deploy
```

### View Logs

```bash
php artisan coolify:logs
php artisan coolify:logs --follow
```

### Provision Infrastructure

Create your entire stack on Coolify interactively:

```bash
php artisan coolify:provision
```

Or non-interactively:

```bash
php artisan coolify:provision \
  --name=myapp \
  --domain=myapp.example.com \
  --with-postgres \
  --with-dragonfly \
  --force
```

**Prerequisites:**

1. Create an SSH key in Coolify (Security > Private Keys)
2. Set `COOLIFY_DEPLOY_KEY_UUID` in your `.env` (or select during provisioning)

**After Provisioning:**

1. Add the public key to your GitHub repo (Settings > Deploy Keys)
2. Set up a webhook for auto-deploy on push (Settings > Webhooks)
   - The provisioning command will show you the exact URLs and secrets needed

## Dashboard

Access the dashboard at `/coolify` (configurable via `COOLIFY_PATH`).

The dashboard provides a tabbed interface for managing your infrastructure:

**Deployments Tab**
- Recent deployment history with status indicators
- Click any deployment to view build logs
- Commit messages and deployment duration

**App Logs Tab**
- Real-time application logs
- Refresh on demand

**Resources Tab**
- Database and Redis status with connection info
- Start/Stop/Restart controls for each resource
- Image versions and resource limits

**Environment Tab**
- View all environment variables
- Add new variables (build-time or runtime)
- Delete variables

**Settings Tab**
- Application details (repository, branch, build pack)
- Project and environment info
- Resource UUIDs for reference

**Deploy Key Section**
- Shows the public key for your deploy key
- Direct link to add it to your GitHub repository

### Authorization

By default, the dashboard is only accessible in `local` environment. To allow access in production, configure the gate in your `CoolifyServiceProvider`:

```php
use Stumason\Coolify\Coolify;

Coolify::auth(function ($request) {
    return in_array($request->user()?->email, [
        'admin@example.com',
    ]);
});
```

## Artisan Commands

| Command | Description |
|---------|-------------|
| `coolify:status` | Show application and resource status |
| `coolify:status --all` | Show all applications and databases |
| `coolify:deploy` | Trigger a deployment |
| `coolify:deploy --tag=v1.0.0` | Deploy a specific git tag |
| `coolify:deploy --wait` | Wait for deployment to complete |
| `coolify:restart` | Restart the application |
| `coolify:rollback` | Rollback to a previous deployment |
| `coolify:logs` | View application logs |
| `coolify:logs --follow` | Stream logs in real-time |
| `coolify:provision` | Provision new infrastructure |
| `coolify:destroy` | Destroy a project and all its resources |

## Programmatic Usage

Use the `Coolify` facade for programmatic access:

```php
use Stumason\Coolify\Facades\Coolify;

// Deploy
$deployment = Coolify::deploy();

// Get status
$status = Coolify::status();

// Get logs
$logs = Coolify::logs();

// Access repositories directly
$apps = Coolify::applications()->all();
$databases = Coolify::databases()->all();
$servers = Coolify::servers()->all();
```

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=coolify-config
```

Key configuration options:

```php
return [
    'url' => env('COOLIFY_URL', 'https://app.coolify.io'),
    'token' => env('COOLIFY_TOKEN'),
    'application_uuid' => env('COOLIFY_APPLICATION_UUID'),

    // Dashboard settings
    'path' => env('COOLIFY_PATH', 'coolify'),
    'middleware' => ['web'],

    // Associated resources
    'resources' => [
        'database' => env('COOLIFY_DATABASE_UUID'),
        'redis' => env('COOLIFY_REDIS_UUID'),
    ],

    // Performance
    'polling_interval' => env('COOLIFY_POLLING_INTERVAL', 10),
    'cache_ttl' => env('COOLIFY_CACHE_TTL', 30),
];
```

## Notifications

Configure deployment notifications:

```php
use Stumason\Coolify\Coolify;

Coolify::routeSlackNotificationsTo(
    'https://hooks.slack.com/services/xxx',
    '#deployments'
);

Coolify::routeMailNotificationsTo('devops@example.com');
```

## Testing

```bash
composer test
```

## License

MIT
