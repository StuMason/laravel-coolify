# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Laravel package for managing Coolify infrastructure from within a Laravel application. Provides a dashboard, artisan commands, and programmatic API access - similar to how Horizon manages queues.

**Stack:** PHP 8.2+, Laravel 11/12, Pest for testing

## Common Commands

```bash
# Run tests
composer test

# Run tests with coverage
composer test:coverage

# Static analysis
composer lint

# Start workbench server for manual testing
composer serve

# Frontend development
npm run dev      # Development server with HMR
npm run build    # Production build to dist/
```

## Architecture

### Core Components

- [src/CoolifyServiceProvider.php](src/CoolifyServiceProvider.php) - Main service provider
- [src/Coolify.php](src/Coolify.php) - Facade-accessible class with static helpers
- [src/CoolifyClient.php](src/CoolifyClient.php) - HTTP client for Coolify API

### Contracts & Repositories

Interfaces in `src/Contracts/` define the API:
- `ApplicationRepository` - CRUD + deploy/restart/logs
- `DatabaseRepository` - Postgres, MySQL, Redis, Dragonfly
- `DeploymentRepository` - List, trigger, cancel, rollback
- `ServerRepository` - Server management
- `ServiceRepository` - One-click services
- `ProjectRepository` - Projects and environments

Implementations in `src/Repositories/` wrap the `CoolifyClient`.

### Artisan Commands

Located in `src/Console/`:
- `InstallCommand` - Publish config, service provider, and generate Docker files
- `StatusCommand` - Show application/resource status
- `DeployCommand` - Trigger deployments
- `LogsCommand` - View application logs
- `RestartCommand` - Restart application
- `RollbackCommand` - Rollback to previous deployment
- `ProvisionCommand` - Create app + database + redis on Coolify

### HTTP Layer

- Routes: `routes/web.php`
- Controllers: `src/Http/Controllers/`
- Middleware: `src/Http/Middleware/Authenticate.php`
- Views: `resources/views/`

### Dashboard Frontend

The dashboard is a Vue 3 SPA built with Vite and Tailwind CSS.

- Entry: `resources/js/app.js` - Vue app with Vue Router
- Pages: `resources/js/pages/` - Dashboard, Deployments, Resources, etc.
- Components: `resources/js/components/` - Layout, CommandPalette, Toast
- Styles: `resources/css/app.css` - Tailwind directives
- Build output: `dist/` - Published to `public/vendor/coolify/`

### Testing

Uses Orchestra Testbench for Laravel package testing with Pest.

- `tests/Unit/` - CoolifyClient, repositories, facade
- `tests/Feature/Console/` - Artisan command tests
- `tests/Feature/Http/` - Dashboard and API endpoint tests

All HTTP calls are mocked using `Http::fake()`.

### Docker Generation

- `src/Docker/DockerGenerator.php` - Multi-stage Dockerfile generator
- `src/Detectors/` - Package detectors (Horizon, Reverb, Scheduler)
- Auto-detects Laravel packages and configures supervisor workers
- Generates production-ready Dockerfile, nginx.conf, supervisord.conf, php.ini
- Configurable via `config/coolify.php` under the `docker` key

## Key Patterns

1. **Repository Pattern** - Contracts define the interface, implementations use CoolifyClient
2. **Service Bindings** - Defined in `ServiceBindings` trait, bound in service provider
3. **Authentication** - `Coolify::auth()` callback, defaults to local-only access
4. **Caching** - API responses cached with configurable TTL, mutations clear cache

## Git Workflow

**NEVER push directly to main.** Always follow this workflow:

1. Create a feature branch from main
2. Make changes and commit
3. Run tests (`composer test`) and lint (`composer lint`)
4. Create a PR to main
5. Address review feedback
6. Merge PR
7. Tag release if needed

```bash
# Example workflow
git checkout -b feature/my-feature
# ... make changes ...
composer test && composer lint
git add -A && git commit -m "feat: description"
git push -u origin feature/my-feature
gh pr create --title "feat: description" --body "..."
# ... address feedback ...
gh pr merge
git checkout main && git pull
git tag v1.x.x && git push origin v1.x.x
```

## Future Tasks

- [ ] Add Nightwatch detector (Laravel monitoring package)
