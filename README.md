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

## Dashboard

Access the dashboard at `/coolify` (configurable via `COOLIFY_PATH`).

The dashboard shows:
- Application status and git info
- Database status
- Recent deployments
- Deploy and restart buttons

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
