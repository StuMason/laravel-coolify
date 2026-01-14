# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Database storage for resource configuration (`coolify_resources` table)
- `CoolifyResource` Eloquent model with `getDefault()` helper

### Changed
- Resource UUIDs now stored in database instead of `.env` file
- All commands read from database via `CoolifyResource::getDefault()`
- `coolify:provision` saves to database instead of updating `.env`

### Removed
- `COOLIFY_APPLICATION_UUID`, `COOLIFY_SERVER_UUID`, `COOLIFY_PROJECT_UUID` env vars
- `COOLIFY_DEPLOY_KEY_UUID`, `COOLIFY_DATABASE_UUID`, `COOLIFY_REDIS_UUID` env vars

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
