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
- `InstallCommand` - Publish config and service provider
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

Dashboard is a Blade template with Alpine.js for interactivity.

### Testing

Uses Orchestra Testbench for Laravel package testing with Pest.

- `tests/Unit/` - CoolifyClient, repositories, facade
- `tests/Feature/Console/` - Artisan command tests
- `tests/Feature/Http/` - Dashboard and API endpoint tests

All HTTP calls are mocked using `Http::fake()`.

## Key Patterns

1. **Repository Pattern** - Contracts define the interface, implementations use CoolifyClient
2. **Service Bindings** - Defined in `ServiceBindings` trait, bound in service provider
3. **Authentication** - `Coolify::auth()` callback, defaults to local-only access
4. **Caching** - API responses cached with configurable TTL, mutations clear cache
