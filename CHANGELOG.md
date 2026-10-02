# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [3.5.0] - 2026-10-02

### Security
- Dashboard API route parameters are restricted to letters, digits and hyphens, so encoded `..`, `#` and `?` can no longer redirect a dashboard action to a different Coolify endpoint
- `coolify:provision` no longer copies the operator's `COOLIFY_TOKEN` into the provisioned app. To use the dashboard in production, create a separate, minimal-scope token in Coolify and set it on the app
- `coolify:provision` marks every env var except `VITE_*` as runtime-only, so secrets such as `APP_KEY` and `DB_PASSWORD` are no longer passed as Docker build args
- Dashboard actions (non-GET requests) are rate-limited to 30 per minute per user; reads are unlimited
- Application log requests are clamped to 1-1000 lines, and the dashboard's `environment` query parameter is validated
- Require `guzzlehttp/guzzle` ^7.15.2 (GHSA-v5mv-p594-2x33, GHSA-f7vp-7xgx-4w4r)
- Generated container runs boot-time `artisan` (`db:show`, `migrate`, `optimize`) as `www-data` via `runuser`, not root. Storage is restored before artisan runs, and `public/storage` is linked at build time (#142)
- `coolify:provision` no longer puts the webhook secret in the webhook URL, and doesn't print it with `--no-interaction` (#143)
- `coolify:provision` generates fresh Reverb credentials for production instead of copying local ones (#143)
- `coolify:provision` keeps `.env` file permissions when updating it (#143)
- Deploy keypair is generated in a private, randomly named temp directory that is always removed (#143)
- Kick config (including `KICK_TOKEN`) is no longer cached in the host app's cache under `kick.config.{uuid}` (#143)
- CI: narrower `claude.yml` tool allowlist, Dependabot 7-day cooldown, `dist/` built without install scripts or a write token, actions pinned to commit SHAs (#141)

### Fixed
- Re-running `coolify:provision` no longer throws a `TypeError`. Existing env vars are updated through Coolify's key-based `PATCH /applications/{uuid}/envs`, and an existing `APP_KEY` is never replaced (#144)
- Deploys use `POST /deploy` and are never cached. Current Coolify rejects `GET /deploy`, and repeat deploys within the cache TTL previously did nothing (#145)
- The dashboard sends `is_buildtime` and `domains`, the field names Coolify's API accepts, instead of `is_build_time` and `fqdn` (#146)

### Removed
- `coolify.kick.cache_ttl` / `COOLIFY_KICK_CACHE_TTL`: Kick lookups use the API response cache (`coolify.cache_ttl`) (#143)

**Upgrade notes:**
- Apps provisioned before 3.5.0 still have `COOLIFY_TOKEN` set in Coolify. Remove it from apps that don't use the dashboard, or replace it with a minimal-scope token, and rotate the operator token.
- The package only generates Docker files; it never rewrites existing ones. Regenerate them (`php artisan coolify:install`) to pick up the non-root entrypoint and build-time `storage:link`.

## [3.4.2] - 2026-10-02

### Fixed
- `coolify:provision` derives a valid Postgres database name from any app name (hyphens, spaces, accents, a leading digit, over 63 characters), so provisioning no longer fails with `Validation failed.` (#122, #139, fixes #129)
- Coolify API errors now include field-level validation detail, e.g. `Validation failed. (postgres_db: ...)` (#139)
- `coolify:provision` adds the deploy key to GitHub via `gh` before creating the application, so Coolify's key check passes; sets `force_domain_override` to avoid domain conflict errors (#122)
- Deploy key title no longer contains literal quotes (#125)
- Generated Docker setup restores image files under `storage/app` when a volume mount hides them, without overwriting existing uploads (#122)

### Removed
- GitHub App creation path in `coolify:provision`; provisioning uses deploy keys (#122)

## [3.4.1] - 2026-07-18

### Fixed
- Cache invalidation actually works: mutations now forget the cached GET responses this package wrote (tracked in a key registry), instead of forgetting a key that was never written — the dashboard no longer serves pre-mutation state for the rest of the TTL after a deploy/restart/env change (#120)
- `Coolify` cache clearing no longer calls `Cache::flush()` — it never touches the host application's cache store (#120)
- HTTP retries are now GET-only — a timed-out deploy/restart POST can no longer fire the action multiple times (#120)

## [3.4.0] - 2026-07-18

### Added
- PHP 8.5 base images (`ghcr.io/stumason/laravel-coolify-base:8.5` / `8.5-node`); Node bumped to 24 in node images (#92)

### Security
- Generated nginx denies PHP execution under `/storage` and `/uploads` — a dropped `*.php` in an upload path can never reach php-fpm (#109)
- Generated nginx hardening: `server_tokens off`, `X-Content-Type-Options` / `X-Frame-Options` / `Referrer-Policy` on all responses including errors, and refusal of commodity scanner probes (wp-login.php, xmlrpc.php, eval-stdin.php) before they boot PHP (#115)
- Security headers repeated inside the static-asset location — nginx discards inherited `add_header` directives when a location adds its own, so cached assets (including `/storage` uploads) were missing them (#117)

**Upgrade note:** the package only generates Docker files; it never rewrites existing ones. Re-run `php artisan coolify:install` (or regenerate `docker/nginx.conf`) to pick up the nginx changes above.

### Fixed
- PHP 8.5 base image builds: switched to `install-php-extensions` and split opcache into its own layer (#100, #101)

### Changed
- CI: base image rebuilds moved from nightly to fortnightly with minimal-mode caching (#107); nightly 8.5 build and spurious automerge runs fixed (#97); claude-review workflow can now actually post reviews (#118)

### Removed
- Laravel 11 from the supported constraint range — it had been untested since the CI matrix moved to 12/13. Laravel 11 apps stay on v3.3.x.

## [3.3.0] - 2026-03-26

### Added
- Laravel 13 support across all illuminate dependencies

## [3.2.0] - 2026-04-02

### Changed
- Dependency updates and recompiled dashboard assets

## [3.1.1] - 2026-03-03

### Added
- Laravel Kick integration — health/logs/queue/artisan proxying to remote apps (#62)

### Fixed
- Batch fixes for #60, #38, #59, #67, #68 (#70)

## [3.1.0] - 2026-01-23

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
