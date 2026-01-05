# Plan: New laravel-coolify Package

## Overview

A complete Laravel package for deploying to and managing Coolify infrastructure. Combines the lessons learned from `stumason/laravel-coolify` (v0.9.10) and `laravel-coolify-starter`.

**Complementary to Claudavel:** Claudavel handles opinionated Laravel setup (packages, coding standards, Actions/DTOs). This package handles deployment to Coolify.

---

## Package Scope

### What This Package Does

1. **Dashboard** - Blade + Alpine.js monitoring dashboard for Coolify resources
2. **API Client** - Full Coolify API wrapper with repository pattern
3. **Artisan Commands** - Deploy, logs, status, rollback, provision, install
4. **Nixpacks Generation** - Intelligent `nixpacks.toml` based on detected packages
5. **Environment Management** - Smart env var handling during provisioning

### What This Package Does NOT Do

- Install Laravel packages (that's Claudavel's job)
- Set up coding standards (that's Claudavel's job)
- Configure local dev environment (that's Claudavel's job)

---

## Architecture

### Directory Structure

```
stumason/laravel-coolify/
├── config/
│   └── coolify.php
├── database/
│   └── migrations/           # Optional: local deployment tracking
├── resources/
│   └── views/
│       └── dashboard/        # Blade templates
├── routes/
│   └── web.php               # Dashboard routes
├── src/
│   ├── Contracts/            # Repository interfaces
│   ├── Repositories/         # Coolify API implementations
│   ├── Console/              # Artisan commands
│   │   ├── StatusCommand.php
│   │   ├── DeployCommand.php
│   │   ├── LogsCommand.php
│   │   ├── RestartCommand.php
│   │   ├── RollbackCommand.php
│   │   ├── ProvisionCommand.php
│   │   └── InstallCommand.php
│   ├── Http/
│   │   ├── Controllers/
│   │   └── Middleware/
│   ├── Nixpacks/             # Nixpacks generation
│   │   ├── NixpacksGenerator.php
│   │   └── Detectors/        # Package detection
│   ├── Support/              # Helpers, traits
│   ├── Coolify.php           # Facade class
│   ├── CoolifyClient.php     # HTTP client
│   └── CoolifyServiceProvider.php
├── stubs/
│   └── nixpacks.toml.stub    # Base template
├── tests/
│   ├── Unit/
│   └── Feature/
├── composer.json
└── README.md
```

---

## Core Components (Migrate from Current)

### 1. CoolifyClient

The HTTP client wrapper. **Migrate as-is** with minor improvements.

```php
class CoolifyClient
{
    public function get(string $endpoint): array;
    public function post(string $endpoint, array $data = []): array;
    public function patch(string $endpoint, array $data = []): array;
    public function delete(string $endpoint): bool;
}
```

### 2. Repository Contracts & Implementations

**Migrate as-is** from current package:

| Contract | Purpose |
|----------|---------|
| `ApplicationRepository` | CRUD + deploy/restart/logs/env |
| `DatabaseRepository` | Postgres, MySQL, Redis, etc. |
| `DeploymentRepository` | List, trigger, cancel, rollback |
| `ServerRepository` | Server management |
| `ServiceRepository` | One-click services |
| `ProjectRepository` | Projects and environments |
| `TeamRepository` | Team management |
| `GitHubAppRepository` | GitHub App integration |
| `SecurityKeyRepository` | SSH key management |

### 3. Dashboard

**Migrate as-is** - Blade + Alpine.js dashboard showing:
- Server status
- Application list with health indicators
- Recent deployments
- Quick actions (deploy, restart)

### 4. Artisan Commands

**Migrate and enhance:**

| Command | Status | Notes |
|---------|--------|-------|
| `coolify:status` | Migrate | Show app/server status |
| `coolify:deploy` | Migrate | Trigger deployment |
| `coolify:logs` | Migrate | View app logs |
| `coolify:restart` | Migrate | Restart application |
| `coolify:rollback` | Migrate | Rollback to previous |
| `coolify:provision` | Migrate | Zero-to-deployed workflow |
| `coolify:install` | **New** | Publish config, generate nixpacks |

---

## New Feature: Intelligent Nixpacks Generation

The killer feature that makes this package valuable.

### How It Works

1. `coolify:install` detects installed Laravel packages
2. Generates optimized `nixpacks.toml` for those packages
3. Handles Horizon, Reverb, Telescope, Scheduler, etc.

### Package Detection

```php
namespace Stumason\Coolify\Nixpacks\Detectors;

class HorizonDetector implements PackageDetector
{
    public function isInstalled(): bool
    {
        return class_exists(\Laravel\Horizon\Horizon::class);
    }

    public function getProcesses(): array
    {
        return [
            'horizon' => 'php artisan horizon',
        ];
    }

    public function getEnvVars(): array
    {
        return [
            'HORIZON_ENABLED' => 'true',
        ];
    }
}
```

### Detectors Needed

| Detector | Processes | Notes |
|----------|-----------|-------|
| `HorizonDetector` | `php artisan horizon` | Redis queue management |
| `ReverbDetector` | `php artisan reverb:start` | WebSocket server |
| `SchedulerDetector` | `php artisan schedule:work` | Task scheduling |
| `OctaneDetector` | `php artisan octane:start` | High-performance server |
| `PulseDetector` | None (dashboard only) | May need Redis config |

### Generated nixpacks.toml Example

```toml
[phases.setup]
nixPkgs = ["...", "php83Extensions.redis", "nodejs_20"]

[phases.build]
cmds = [
    "composer install --no-dev --optimize-autoloader",
    "npm ci && npm run build",
    "php artisan config:cache",
    "php artisan route:cache",
    "php artisan view:cache",
]

[start]
cmd = "php-fpm"

[processes]
web = "php-fpm"
horizon = "php artisan horizon"
reverb = "php artisan reverb:start --host=0.0.0.0 --port=8080"
scheduler = "php artisan schedule:work"
```

---

## coolify:install Command

New command that sets up everything for Coolify deployment.

### What It Does

1. Publishes `config/coolify.php`
2. Detects installed packages (Horizon, Reverb, etc.)
3. Generates optimized `nixpacks.toml`
4. Adds Coolify-specific entries to `.gitignore`
5. Optionally creates health check endpoint

### Signature

```php
protected $signature = 'coolify:install
    {--force : Overwrite existing files}
    {--no-nixpacks : Skip nixpacks.toml generation}
    {--no-health : Skip health check endpoint}';
```

### Output

```
Installing Laravel Coolify...

Detected packages:
  ✓ Laravel Horizon (queue management)
  ✓ Laravel Reverb (WebSockets)
  ✓ Laravel Telescope (debugging)

Generated nixpacks.toml with:
  • web process (php-fpm)
  • horizon process
  • reverb process
  • scheduler process

Published config/coolify.php

Next steps:
  1. Set COOLIFY_URL and COOLIFY_TOKEN in .env
  2. Run `php artisan coolify:provision` to deploy
  3. Visit /coolify to access the dashboard
```

---

## coolify:provision Enhancements

Improvements to the provisioning workflow.

### Current Flow (Keep)

1. Select/create server
2. Select/create project
3. Configure GitHub repository
4. Create application
5. Optionally create database + Redis
6. Set environment variables
7. Trigger first deployment

### Enhancements

1. **Auto-detect nixpacks.toml** - If exists, use it; otherwise generate on-the-fly
2. **Better env var handling** - Already improved in v0.9.10 (REVERB_*, VITE_*, APP_URL, etc.)
3. **Post-deployment verification** - Wait for deployment, check health endpoint
4. **Staging/Production selection** - Create both environments in one flow

---

## Configuration

### config/coolify.php

```php
return [
    // Coolify instance URL
    'url' => env('COOLIFY_URL'),

    // API token
    'token' => env('COOLIFY_TOKEN'),

    // Dashboard settings
    'dashboard' => [
        'enabled' => env('COOLIFY_DASHBOARD_ENABLED', true),
        'path' => env('COOLIFY_DASHBOARD_PATH', 'coolify'),
        'middleware' => ['web', 'auth'],
    ],

    // Cache settings
    'cache' => [
        'enabled' => env('COOLIFY_CACHE_ENABLED', true),
        'ttl' => env('COOLIFY_CACHE_TTL', 60), // seconds
    ],

    // Resource UUIDs (set by coolify:provision)
    'server_uuid' => env('COOLIFY_SERVER_UUID'),
    'project_uuid' => env('COOLIFY_PROJECT_UUID'),
    'application_uuid' => env('COOLIFY_APPLICATION_UUID'),
];
```

---

## Testing Strategy

### Unit Tests

- `CoolifyClient` - Mock HTTP responses
- Repository classes - Mock client
- Nixpacks generators - File assertions
- Package detectors - Class existence checks

### Feature Tests

- Dashboard routes - Authentication, response codes
- Artisan commands - Output assertions, mock API calls
- Install command - File creation assertions

### Integration Tests (Optional)

- Real Coolify API tests (skipped by default)
- Require `COOLIFY_TEST_URL` and `COOLIFY_TEST_TOKEN`

---

## Migration Path from Current Package

### For Existing Users

1. Update to new package version
2. Run `php artisan coolify:install --force`
3. Review generated `nixpacks.toml`
4. Redeploy via `php artisan coolify:deploy`

### Breaking Changes

- Namespace change: `Stumason\Coolify` (keep same for compatibility?)
- Config file structure may change
- Some command signatures may change

---

## Pro Version Ideas (Future)

Features that could be gated behind a paid license:

1. **Multi-environment management** - staging → production promotion
2. **Deployment pipelines** - Automated staging → production with approval gates
3. **Slack/Discord notifications** - Deployment status alerts
4. **Resource monitoring** - CPU/memory graphs in dashboard
5. **Cost tracking** - Server costs aggregation
6. **Backup management** - Database backup scheduling
7. **Log aggregation** - Centralized log viewing across apps
8. **Team management** - Invite team members, role-based access

---

## Development Phases

### Phase 1: Core Migration

- [ ] Create new package from Spatie skeleton
- [ ] Migrate CoolifyClient
- [ ] Migrate all repositories
- [ ] Migrate all commands (except provision)
- [ ] Migrate dashboard
- [ ] Write tests

### Phase 2: Nixpacks Generation

- [ ] Create NixpacksGenerator class
- [ ] Implement package detectors (Horizon, Reverb, Scheduler, Octane)
- [ ] Create `coolify:install` command
- [ ] Generate intelligent nixpacks.toml

### Phase 3: Enhanced Provisioning

- [ ] Migrate and enhance ProvisionCommand
- [ ] Add nixpacks detection/generation during provision
- [ ] Add post-deployment health check
- [ ] Add staging/production environment support

### Phase 4: Polish

- [ ] Documentation (README, inline docs)
- [ ] GitHub Actions CI
- [ ] Packagist publishing
- [ ] Real-world testing with Claudavel projects

---

## Decisions Made

1. **Namespace:** Keep `Stumason\Coolify` ✓
2. **Dashboard:** Blade + Alpine (keep it simple) ✓
3. **Pro features:** Same package with license check ✓
4. **Starter integration:** TBD - consider suggesting Claudavel if not detected

---

## Summary

The new `laravel-coolify` package focuses on one thing: **making Coolify deployments effortless**.

- **Migrate** the solid foundation (client, repositories, commands, dashboard)
- **Add** intelligent nixpacks generation (the differentiator)
- **Enhance** provisioning workflow
- **Complement** Claudavel for complete Laravel → Coolify workflow

Together:
1. `claudavel:install --all` → Get opinionated Laravel setup
2. `coolify:install` → Generate optimized nixpacks.toml
3. `coolify:provision` → Zero-to-deployed in minutes
