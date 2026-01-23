# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Multi-environment support in dashboard with environment switcher
- Environment badge displayed prominently in dashboard header
- Stats endpoint accepts `?environment=` query parameter

### Changed
- Dashboard fetches resources from environment endpoint instead of global endpoints
- Application lookup now uses environment's applications array instead of git repository matching

### Removed
- Dead migration code referencing non-existent `coolify_resources` table

## [3.0.0] - 2026-01-22

### Added
- Pre-built Docker base images for faster deployments (~12 min to ~2-3 min)
  - `ghcr.io/stumason/laravel-coolify-base:8.3` / `8.4` / `8.3-node` / `8.4-node`
  - GitHub Actions workflow for nightly security patch rebuilds
  - Multi-architecture support (amd64, arm64)
- Database connection wait with retry before running migrations
- Configuration options for deployment behavior:
  - `COOLIFY_USE_BASE_IMAGE` - Use pre-built base images (default: true)
  - `COOLIFY_AUTO_MIGRATE` - Run migrations on startup (default: true)
  - `COOLIFY_DB_WAIT_TIMEOUT` - DB wait timeout in seconds (default: 30)

### Changed
- Dockerfile generator now uses base images by default for faster builds
- Auto-detect Node.js requirement from `package.json` for base image selection
- Entrypoint script now waits for database connection before migrating

### Removed
- `CoolifyResource` Eloquent model (resources now fetched directly from API)
- Application/database/server UUID environment variables (only `COOLIFY_PROJECT_UUID` needed)

## [2.9.0] - 2026-01-20

### Added
- Documentation site built with Astro Starlight

## [2.8.0] - 2026-01-15

### Added
- Docker entrypoint script for production deployments
  - Runs `migrate --force` on container startup (fails deployment if migrations fail)
  - Runs `php artisan optimize` (config, routes, views, events cache)
  - Ensures storage link exists

### Changed
- Dockerfile now uses `ENTRYPOINT` instead of `CMD` for proper startup sequence

## [2.7.0] - 2026-01-14

### Added
- Starlight documentation site at `/docs`

## [2.6.0] - 2026-01-14

### Added
- GitHub Actions workflow generation via `coolify:setup-ci` command
- Auto-deployment configuration

## [2.5.0] - 2026-01-14

### Changed
- Replace Nixpacks with multi-stage Dockerfile generation
- Generated Dockerfile includes PHP-FPM, Nginx, Supervisor

### Fixed
- Handle void return type in TrustProxies regex pattern

## [2.4.0] - 2026-01-06

### Added
- Improved provisioning experience with better defaults
- Pre-select current git repository in selection

### Fixed
- Remove www-data user directive from nginx
- Add libcap and setcap for nginx port 80 binding
- Run npm build in postbuild phase for Wayfinder plugin support
- Run composer in postbuild phase to ensure vendor persists
- Improve log coloring - stderr yellow, errors red

## [2.3.0] - 2026-01-06

### Added
- Confirmation prompts for deploy key and webhook setup

### Fixed
- Remove invalid default parameter from search() function

## [2.2.0] - 2026-01-06

### Added
- Production-ready provisioning with pre-flight checks
- Log streaming during deployments
- API token setup guidance with screenshot

### Fixed
- Combine npm ci and npm run build to fix vite not found error

## [2.1.0] - 2026-01-06

### Added
- Improved nixpacks.toml generator for faster builds

## [2.0.0] - 2026-01-06

### Added
- Initial release with Coolify API integration
- Dashboard for monitoring applications
- Artisan commands: provision, deploy, status, logs, restart, rollback
- Repository pattern for API access
- Event system for deployment notifications
