# Laravel Coolify

[![Tests](https://github.com/StuMason/laravel-coolify/actions/workflows/tests.yml/badge.svg)](https://github.com/StuMason/laravel-coolify/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/stumason/laravel-coolify.svg)](https://packagist.org/packages/stumason/laravel-coolify)
[![PHP Version](https://img.shields.io/packagist/php-v/stumason/laravel-coolify.svg)](https://packagist.org/packages/stumason/laravel-coolify)
[![Laravel Version](https://img.shields.io/badge/laravel-11.x%20|%2012.x-red.svg)](https://laravel.com)
[![License](https://img.shields.io/packagist/l/stumason/laravel-coolify.svg)](LICENSE)
[![Downloads](https://img.shields.io/packagist/dt/stumason/laravel-coolify.svg)](https://packagist.org/packages/stumason/laravel-coolify)

**Deploy Laravel to [Coolify](https://coolify.io) with one command. Dashboard, CLI, and Dockerfile generation included.**

Like Laravel Horizon for queues, but for your entire infrastructure.

![Laravel Coolify Dashboard](docs/dashboard.png)

## Why This Exists

Self-hosting with Coolify is great, but managing deployments from the Coolify UI gets tedious. This package gives you:

- **A beautiful dashboard** inside your Laravel app - deploy, restart, view logs, manage env vars
- **Artisan commands** for everything - CI/CD pipelines, local development, scripting
- **Production-ready Dockerfiles** generated automatically - no Docker knowledge required
- **Full API access** to Coolify - build custom tooling, automations, whatever you need

## Quick Start

```bash
composer require stumason/laravel-coolify
php artisan coolify:install
```

Add to `.env`:

```env
COOLIFY_URL=https://your-coolify.com
COOLIFY_TOKEN=your-api-token
```

Then provision your infrastructure:

```bash
php artisan coolify:provision
```

Creates app + PostgreSQL + Dragonfly on Coolify and deploys. One command.

## Documentation

**[Read the full docs](https://stumason.github.io/laravel-coolify)**

## Dashboard Features

Access at `/coolify` (configurable) - works like Horizon's dashboard.

| Feature | Description |
|---------|-------------|
| **Live Status** | Real-time application health with animated indicators |
| **One-Click Deploy** | Deploy latest, force rebuild, or deploy specific commits |
| **Deployment History** | View all deployments with inline expandable build logs |
| **Database Management** | Start/stop/restart PostgreSQL, MySQL, Redis, Dragonfly |
| **Environment Variables** | Secure CRUD for env vars with masked values |
| **Build Logs** | Stream deployment logs in real-time |
| **GitHub Integration** | Links to commits, branches, repository |
| **Coolify Deep Links** | Jump directly to resources in Coolify UI |

### Dashboard Screenshots

The dashboard shows everything at a glance:

- Application status with health checks
- Current branch, commit, and last deploy time
- Database and cache status with connection strings
- Recent deployments with inline build logs
- Quick actions for deploy, restart, stop

## Artisan Commands

| Command | Description |
|---------|-------------|
| `coolify:install` | Publish config, generate Dockerfile |
| `coolify:provision` | Create infrastructure on Coolify |
| `coolify:deploy` | Trigger deployment |
| `coolify:status` | Show application status |
| `coolify:logs` | View application logs |
| `coolify:restart` | Restart application |
| `coolify:rollback` | Rollback to previous deployment |

### CI/CD Integration

```yaml
# .github/workflows/deploy.yml
- name: Deploy to Coolify
  run: php artisan coolify:deploy --force
  env:
    COOLIFY_URL: ${{ secrets.COOLIFY_URL }}
    COOLIFY_TOKEN: ${{ secrets.COOLIFY_TOKEN }}
```

## Dockerfile Generation

The install command generates production-optimized Docker configuration:

```bash
php artisan coolify:install
```

Creates:
- `Dockerfile` - Multi-stage build with OPcache, proper permissions
- `docker/nginx.conf` - Optimized for Laravel
- `docker/supervisord.conf` - Process management (Horizon, Reverb, Scheduler auto-detected)
- `docker/php.ini` - Production PHP settings

Auto-detects and configures:
- Laravel Horizon (queue workers)
- Laravel Reverb (WebSockets)
- Laravel Scheduler

## Programmatic API

```php
use Stumason\Coolify\Coolify;

// Deploy
Coolify::deploy();
Coolify::deploy('custom-uuid');

// Status
$status = Coolify::status();
$logs = Coolify::logs();

// Repositories for full control
Coolify::applications()->get($uuid);
Coolify::applications()->deploy($uuid, force: true);
Coolify::applications()->restart($uuid);
Coolify::applications()->envs($uuid);

Coolify::databases()->all();
Coolify::databases()->start($uuid);

Coolify::deployments()->forApplication($uuid);
Coolify::deployments()->cancel($uuid);

Coolify::servers()->all();
Coolify::services()->all();
```

## Configuration

```php
// config/coolify.php
return [
    'url' => env('COOLIFY_URL'),
    'token' => env('COOLIFY_TOKEN'),
    'path' => env('COOLIFY_PATH', 'coolify'),  // Dashboard URL path

    'docker' => [
        'php_version' => '8.3',
        'node_version' => '20',
        'extensions' => ['pdo_pgsql', 'redis', 'pcntl', 'bcmath'],
    ],
];
```

## Authentication

By default, the dashboard is only accessible in `local` environment. For production:

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

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- Coolify 4.x instance with API access

## Testing

```bash
composer test          # Run tests
composer test:coverage # With coverage
composer lint          # Static analysis
```

## Contributing

Contributions welcome! Please read [CONTRIBUTING.md](CONTRIBUTING.md) first.

## License

MIT - see [LICENSE](LICENSE)

## Credits

- [Stu Mason](https://github.com/StuMason)
- [All Contributors](../../contributors)
