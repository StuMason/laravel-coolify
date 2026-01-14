# Laravel Coolify

Deploy Laravel to [Coolify](https://coolify.io) with one command. Dashboard, CLI, and Dockerfile generation included.

## Quick Start

```bash
composer require stumason/laravel-coolify
php artisan coolify:install
php artisan migrate
```

Add to `.env`:

```env
COOLIFY_URL=https://your-coolify.com
COOLIFY_TOKEN=your-api-token
```

Then:

```bash
php artisan coolify:provision
```

Creates app + PostgreSQL + Dragonfly on Coolify and deploys.

## Documentation

**[Read the docs](https://stumason.github.io/laravel-coolify)**

## Commands

| Command | Description |
|---------|-------------|
| `coolify:install` | Publish config, generate Dockerfile |
| `coolify:provision` | Create infrastructure on Coolify |
| `coolify:deploy` | Trigger deployment |
| `coolify:status` | Show application status |
| `coolify:logs` | View logs |
| `coolify:restart` | Restart application |
| `coolify:rollback` | Rollback deployment |

## License

MIT
