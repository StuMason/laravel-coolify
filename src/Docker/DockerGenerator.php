<?php

declare(strict_types=1);

namespace Stumason\Coolify\Docker;

use Illuminate\Support\Facades\File;
use Stumason\Coolify\Detectors\HorizonDetector;
use Stumason\Coolify\Detectors\PackageDetector;
use Stumason\Coolify\Detectors\ReverbDetector;
use Stumason\Coolify\Detectors\SchedulerDetector;

class DockerGenerator
{
    /** @var array<PackageDetector> */
    protected array $detectors = [];

    /** @var array<PackageDetector> */
    protected array $detected = [];

    protected bool $hasDetected = false;

    protected ?bool $hasNode = null;

    protected ?string $databaseType = null;

    protected ?bool $hasBrowsershot = null;

    public function __construct()
    {
        $this->detectors = [
            new HorizonDetector,
            new ReverbDetector,
            new SchedulerDetector,
        ];
    }

    /**
     * Run detection and return list of detected packages.
     *
     * @return array<PackageDetector>
     */
    public function detect(): array
    {
        if ($this->hasDetected) {
            return $this->detected;
        }

        $this->detected = [];

        foreach ($this->detectors as $detector) {
            if ($detector->isInstalled()) {
                $this->detected[] = $detector;
            }
        }

        $this->hasDetected = true;

        return $this->detected;
    }

    /**
     * @return array<PackageDetector>
     */
    public function getDetected(): array
    {
        return $this->detected;
    }

    /**
     * Write all Docker configuration files.
     *
     * @return array<string, string> Map of file paths that were written
     */
    public function write(?string $basePath = null): array
    {
        $basePath = $basePath ?? base_path();

        // Ensure docker directory exists
        $dockerDir = $basePath.'/docker';
        if (! File::isDirectory($dockerDir)) {
            File::makeDirectory($dockerDir, 0755, true);
        }

        $files = [
            'Dockerfile' => $this->generateDockerfile(),
            'docker/supervisord.conf' => $this->generateSupervisordConf(),
            'docker/nginx.conf' => $this->generateNginxConf(),
            'docker/php.ini' => $this->generatePhpIni(),
            'docker/entrypoint.sh' => $this->generateEntrypoint(),
        ];

        $written = [];
        foreach ($files as $relativePath => $content) {
            $fullPath = $basePath.'/'.$relativePath;
            File::put($fullPath, $content);
            $written[$relativePath] = $fullPath;
        }

        return $written;
    }

    /**
     * Check if Docker files already exist.
     */
    public function exists(): bool
    {
        return File::exists(base_path('Dockerfile'));
    }

    /**
     * Generate the Dockerfile content.
     */
    public function generateDockerfile(): string
    {
        $useBaseImage = config('coolify.docker.use_base_image', true);

        if ($useBaseImage) {
            return $this->generateDockerfileWithBaseImage();
        }

        return $this->generateDockerfileFromScratch();
    }

    /**
     * Generate Dockerfile using pre-built base image (fast builds).
     */
    protected function generateDockerfileWithBaseImage(): string
    {
        $phpVersion = config('coolify.docker.php_version') ?? '8.4';
        $healthCheckPath = config('coolify.docker.health_check_path') ?? '/up';
        $autoMigrate = config('coolify.docker.auto_migrate', true) ? 'true' : 'false';
        $dbWaitTimeout = config('coolify.docker.db_wait_timeout', 30);

        $hasNode = $this->hasNodeDependencies();
        $baseImageTag = $hasNode ? "{$phpVersion}-node" : $phpVersion;
        $frontendStage = $hasNode ? $this->getFrontendBuildStage() : '';
        $frontendCopy = $hasNode ? 'COPY --from=frontend-build /app/public/build ./public/build' : '# No frontend build (no package.json detected)';

        $browsershotEnv = $this->hasBrowsershot()
            ? "\nENV CHROMIUM_PATH=/usr/bin/chromium \\\n    BROWSERSHOT_CHROME_PATH=/usr/bin/chromium"
            : '';

        return <<<DOCKERFILE
# ============================================
# Stage 1: PHP Dependencies
# ============================================
FROM composer:2 AS composer-deps

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \\
    --no-dev \\
    --no-scripts \\
    --no-autoloader \\
    --prefer-dist \\
    --ignore-platform-reqs
{$frontendStage}
# ============================================
# Stage 2: Production Image
# ============================================
# Using pre-built base image with PHP extensions already compiled.
# This reduces build time from ~12 minutes to ~2-3 minutes.
# To build from scratch instead, set COOLIFY_USE_BASE_IMAGE=false
FROM ghcr.io/stumason/laravel-coolify-base:{$baseImageTag} AS production

LABEL maintainer="Laravel Coolify" \\
      description="Laravel application deployed via Coolify"

# Custom PHP config
COPY docker/php.ini "\$PHP_INI_DIR/conf.d/99-custom.ini"

# Nginx config
COPY docker/nginx.conf /etc/nginx/nginx.conf

# Supervisor config
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

WORKDIR /var/www/html

# Copy composer binary and dependencies from build stages
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=composer-deps /app/vendor ./vendor

# Copy application code (order matters for caching!)
COPY artisan ./
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY public ./public
{$frontendCopy}
COPY routes ./routes
COPY storage ./storage
COPY resources/views ./resources/views
COPY app ./app
COPY composer.json composer.lock ./

# Generate optimized autoloader
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \\
    && chmod -R 775 storage bootstrap/cache

# Entrypoint configuration
ENV AUTO_MIGRATE={$autoMigrate} \\
    DB_WAIT_TIMEOUT={$dbWaitTimeout}

# Entrypoint script (runs migrations + optimize on startup)
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
{$browsershotEnv}

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \\
    CMD curl -f http://localhost:8080{$healthCheckPath} || exit 1

EXPOSE 8080

ENTRYPOINT ["/entrypoint.sh"]
DOCKERFILE;
    }

    /**
     * Generate Dockerfile from scratch (slower but customizable).
     */
    protected function generateDockerfileFromScratch(): string
    {
        $phpVersion = config('coolify.docker.php_version') ?? '8.4';
        $healthCheckPath = config('coolify.docker.health_check_path') ?? '/up';
        $autoMigrate = config('coolify.docker.auto_migrate', true) ? 'true' : 'false';
        $dbWaitTimeout = config('coolify.docker.db_wait_timeout', 30);

        $phpExtensions = $this->getPhpExtensions();
        $extensionList = implode(' ', $phpExtensions);

        $peclExtensions = $this->getPeclExtensions();
        $peclInstallCommands = implode(" \\\n    && pecl install ", $peclExtensions);
        $peclEnableList = implode(' ', $peclExtensions);

        $systemDeps = $this->getSystemDependencies();
        $systemDepsList = implode(" \\\n        ", $systemDeps);

        $browsershotEnv = $this->hasBrowsershot()
            ? "\nENV CHROMIUM_PATH=/usr/bin/chromium \\\n    BROWSERSHOT_CHROME_PATH=/usr/bin/chromium"
            : '';

        // Build frontend stage only if package.json exists
        $hasNode = $this->hasNodeDependencies();
        $frontendStage = $hasNode ? $this->getFrontendBuildStage() : '';
        $frontendCopy = $hasNode ? 'COPY --from=frontend-build /app/public/build ./public/build' : '# No frontend build (no package.json detected)';

        return <<<DOCKERFILE
# ============================================
# Stage 1: PHP Dependencies
# ============================================
FROM composer:2 AS composer-deps

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \\
    --no-dev \\
    --no-scripts \\
    --no-autoloader \\
    --prefer-dist \\
    --ignore-platform-reqs
{$frontendStage}
# ============================================
# Stage 2: Production Image
# ============================================
# Building from scratch - this takes longer but allows full customization.
# To use pre-built base images instead, set COOLIFY_USE_BASE_IMAGE=true
FROM php:{$phpVersion}-fpm-bookworm AS production

LABEL maintainer="Laravel Coolify" \\
      description="Laravel application deployed via Coolify"

# Install system dependencies
RUN apt-get update \\
    && apt-get install -y --no-install-recommends \\
        {$systemDepsList} \\
    && rm -rf /var/lib/apt/lists/* \\
    && apt-get clean

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \\
    && docker-php-ext-install -j\$(nproc) \\
        {$extensionList} \\
    && pecl install {$peclInstallCommands} \\
    && docker-php-ext-enable {$peclEnableList}

# PHP config
RUN mv "\$PHP_INI_DIR/php.ini-production" "\$PHP_INI_DIR/php.ini"
COPY docker/php.ini "\$PHP_INI_DIR/conf.d/99-custom.ini"

# Nginx config
COPY docker/nginx.conf /etc/nginx/nginx.conf

# Supervisor config
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

WORKDIR /var/www/html

# Copy composer binary and dependencies from build stages
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=composer-deps /app/vendor ./vendor

# Copy application code (order matters for caching!)
COPY artisan ./
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY public ./public
{$frontendCopy}
COPY routes ./routes
COPY storage ./storage
COPY resources/views ./resources/views
COPY app ./app
COPY composer.json composer.lock ./

# Generate optimized autoloader
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \\
    && chmod -R 775 storage bootstrap/cache

# Entrypoint configuration
ENV AUTO_MIGRATE={$autoMigrate} \\
    DB_WAIT_TIMEOUT={$dbWaitTimeout}

# Entrypoint script (runs migrations + optimize on startup)
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
{$browsershotEnv}

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \\
    CMD curl -f http://localhost:8080{$healthCheckPath} || exit 1

EXPOSE 8080

ENTRYPOINT ["/entrypoint.sh"]
DOCKERFILE;
    }

    /**
     * Get the frontend build stage for the Dockerfile.
     * Uses node-only with DOCKER_BUILD=true to skip wayfinder generation.
     * Wayfinder types should be pre-committed or vite.config should skip in CI/Docker.
     */
    protected function getFrontendBuildStage(): string
    {
        return <<<'DOCKERFILE'

# ============================================
# Frontend Build Stage
# ============================================
FROM node:20-alpine AS frontend-build

WORKDIR /app

# Install npm dependencies
COPY package.json package-lock.json* ./
RUN if [ -f package-lock.json ]; then npm ci; else npm install; fi

# Copy frontend assets
COPY vite.config.* tsconfig.json* ./
COPY resources ./resources
COPY public ./public

# Skip wayfinder generation in Docker (types should be pre-committed)
# Add to vite.config: const isCI = process.env.DOCKER_BUILD === 'true'; !isCI && wayfinder()
ENV DOCKER_BUILD=true
RUN npm run build

DOCKERFILE;
    }

    /**
     * Generate supervisord.conf content.
     */
    public function generateSupervisordConf(): string
    {
        $programs = [];

        // Core programs (always present)
        $programs[] = $this->getPhpFpmProgram();
        $programs[] = $this->getNginxProgram();

        // Detected package programs
        foreach ($this->detected as $detector) {
            $program = $this->getDockerSupervisorProgram($detector);
            if ($program !== null) {
                $programs[] = $program;
            }
        }

        $programsStr = implode("\n", $programs);

        return <<<CONF
[supervisord]
nodaemon=true
user=root
logfile=/dev/stdout
logfile_maxbytes=0
pidfile=/run/supervisord.pid

[unix_http_server]
file=/run/supervisor.sock
chmod=0700

[rpcinterface:supervisor]
supervisor.rpcinterface_factory = supervisor.rpcinterface:make_main_rpcinterface

[supervisorctl]
serverurl=unix:///run/supervisor.sock

{$programsStr}
CONF;
    }

    /**
     * Generate nginx.conf content.
     */
    public function generateNginxConf(): string
    {
        $clientMaxBodySize = config('coolify.docker.nginx.client_max_body_size') ?? '35M';
        $uploadMaxFilesize = config('coolify.docker.nginx.upload_max_filesize') ?? '30M';
        $postMaxSize = config('coolify.docker.nginx.post_max_size') ?? '35M';

        // Collect nginx location blocks from detectors
        $extraLocations = [];
        foreach ($this->detected as $detector) {
            $extraLocations = array_merge($extraLocations, $this->getDockerNginxLocations($detector));
        }
        $extraLocationStr = implode("\n\n", $extraLocations);

        return <<<NGINX
worker_processes auto;
pid /run/nginx.pid;
daemon off;

events {
    worker_connections 4096;
    multi_accept on;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    # Logging
    access_log /dev/stdout;
    error_log /dev/stderr;

    # Performance
    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    types_hash_max_size 2048;
    server_names_hash_bucket_size 128;

    # Gzip
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml application/json application/javascript application/xml+rss application/atom+xml image/svg+xml;

    # FastCGI buffer sizes (for large Inertia responses)
    fastcgi_buffer_size 128k;
    fastcgi_buffers 4 256k;
    fastcgi_busy_buffers_size 256k;

    server {
        listen 8080;
        listen [::]:8080;
        server_name _;

        root /var/www/html/public;
        index index.php;

        charset utf-8;
        client_max_body_size {$clientMaxBodySize};

        add_header X-Content-Type-Options "nosniff";

        location / {
            try_files \$uri \$uri/ /index.php?\$query_string;
        }

        location = /favicon.ico { access_log off; log_not_found off; }
        location = /robots.txt  { access_log off; log_not_found off; }

{$extraLocationStr}

        error_page 404 /index.php;

        location ~ \\.php\$ {
            fastcgi_pass 127.0.0.1:9000;
            fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
            include fastcgi_params;

            fastcgi_param PHP_VALUE "upload_max_filesize={$uploadMaxFilesize} \\n post_max_size={$postMaxSize}";

            # Buffer sizes for FastCGI
            fastcgi_buffer_size 128k;
            fastcgi_buffers 4 256k;
            fastcgi_busy_buffers_size 256k;
        }

        location ~ /\\.(?!well-known).* {
            deny all;
        }

        # Cache static assets
        location ~* \\.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)\$ {
            expires 1y;
            add_header Cache-Control "public, immutable";
            access_log off;
        }
    }
}
NGINX;
    }

    /**
     * Generate php.ini content.
     */
    public function generatePhpIni(): string
    {
        $memoryLimit = config('coolify.docker.php.memory_limit') ?? '256M';
        $maxExecutionTime = config('coolify.docker.php.max_execution_time') ?? 60;
        $uploadMaxFilesize = config('coolify.docker.nginx.upload_max_filesize') ?? '30M';
        $postMaxSize = config('coolify.docker.nginx.post_max_size') ?? '35M';

        return <<<INI
; PHP Configuration for Production

; Memory & Execution
memory_limit = {$memoryLimit}
max_execution_time = {$maxExecutionTime}
max_input_time = 60

; File uploads
upload_max_filesize = {$uploadMaxFilesize}
post_max_size = {$postMaxSize}
max_file_uploads = 20

; OPcache (production optimized)
opcache.enable = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0
opcache.save_comments = 1
opcache.enable_file_override = 1

; Error handling
display_errors = Off
display_startup_errors = Off
log_errors = On
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT

; Security
expose_php = Off
session.cookie_httponly = On
session.cookie_secure = On
session.use_strict_mode = On

; Realpath cache
realpath_cache_size = 4096K
realpath_cache_ttl = 600
INI;
    }

    /**
     * Generate entrypoint.sh content.
     * Runs migrations and optimizations before starting supervisor.
     */
    public function generateEntrypoint(): string
    {
        return <<<'BASH'
#!/bin/bash
set -e

echo "============================================"
echo "Laravel Application Startup"
echo "============================================"

# Configuration (set via ENV in Dockerfile)
AUTO_MIGRATE="${AUTO_MIGRATE:-true}"
DB_WAIT_TIMEOUT="${DB_WAIT_TIMEOUT:-30}"

STEP=1
TOTAL_STEPS=3

# ===========================================
# Step 1: Database Migrations (if enabled)
# ===========================================
if [ "$AUTO_MIGRATE" = "true" ]; then
    echo ""
    echo "[$STEP/$TOTAL_STEPS] Waiting for database connection..."

    # Wait for database to be available
    WAITED=0
    until php artisan db:show > /dev/null 2>&1; do
        WAITED=$((WAITED + 1))
        if [ $WAITED -ge $DB_WAIT_TIMEOUT ]; then
            echo "ERROR: Database connection timeout after ${DB_WAIT_TIMEOUT}s" >&2
            echo "       Check that your database is running and accessible." >&2
            exit 1
        fi
        echo "       Waiting for database... ($WAITED/${DB_WAIT_TIMEOUT}s)"
        sleep 1
    done
    echo "       Database connected!"

    echo ""
    echo "[$STEP/$TOTAL_STEPS] Running database migrations..."
    if ! php artisan migrate --force; then
        echo "ERROR: Database migrations failed!" >&2
        echo "       Check migration files and database state." >&2
        exit 1
    fi
    echo "       Migrations completed successfully."
else
    echo ""
    echo "[$STEP/$TOTAL_STEPS] Skipping migrations (AUTO_MIGRATE=false)"
fi
STEP=$((STEP + 1))

# ===========================================
# Step 2: Application Optimization
# ===========================================
echo ""
echo "[$STEP/$TOTAL_STEPS] Optimizing application..."
php artisan optimize
echo "       Optimization completed (config, routes, views, events cached)."
STEP=$((STEP + 1))

# ===========================================
# Step 3: Storage Link
# ===========================================
echo ""
echo "[$STEP/$TOTAL_STEPS] Ensuring storage link..."
php artisan storage:link 2>/dev/null || true
echo "       Storage link ready."

echo ""
echo "============================================"
echo "Application ready. Starting services..."
echo "============================================"
echo ""

# Start supervisor (replaces this process with PID 1)
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
BASH;
    }

    /**
     * PECL extensions that need pecl install instead of docker-php-ext-install.
     */
    protected array $peclExtensions = ['redis', 'imagick', 'xdebug', 'memcached'];

    /**
     * Get PHP extensions to install via docker-php-ext-install.
     *
     * @return array<string>
     */
    protected function getPhpExtensions(): array
    {
        $extensions = [
            'pdo',
            'zip',
            'gd',
            'mbstring',
            'xml',
            'bcmath',
            'intl',
            'opcache',
            'pcntl',
        ];

        // Add database extension
        $dbType = $this->getDatabaseType();
        if ($dbType === 'pgsql') {
            $extensions[] = 'pdo_pgsql';
            $extensions[] = 'pgsql';
        } else {
            $extensions[] = 'pdo_mysql';
        }

        // Add extensions from detectors (filtering out PECL extensions)
        foreach ($this->detected as $detector) {
            $detectorExtensions = $detector->getPhpExtensions();
            foreach ($detectorExtensions as $ext) {
                if (! in_array($ext, $this->peclExtensions)) {
                    $extensions[] = $ext;
                }
            }
        }

        return array_unique($extensions);
    }

    /**
     * Get PECL extensions to install.
     *
     * @return array<string>
     */
    protected function getPeclExtensions(): array
    {
        $pecl = ['redis']; // Always include redis

        // Add PECL extensions from detectors
        foreach ($this->detected as $detector) {
            $detectorExtensions = $detector->getPhpExtensions();
            foreach ($detectorExtensions as $ext) {
                if (in_array($ext, $this->peclExtensions)) {
                    $pecl[] = $ext;
                }
            }
        }

        return array_unique($pecl);
    }

    /**
     * Get system dependencies to install.
     *
     * @return array<string>
     */
    protected function getSystemDependencies(): array
    {
        $deps = [
            '# Core',
            'nginx supervisor curl wget zip unzip git',
            '# PHP extensions deps',
            'libpq-dev libzip-dev libpng-dev libjpeg-dev libfreetype6-dev',
            'libonig-dev libxml2-dev libicu-dev',
        ];

        if ($this->hasBrowsershot()) {
            $deps[] = '# Chromium for Browsershot';
            $deps[] = 'chromium fonts-liberation libappindicator3-1 libasound2';
            $deps[] = 'libatk-bridge2.0-0 libatk1.0-0 libcups2 libdbus-1-3';
            $deps[] = 'libgdk-pixbuf2.0-0 libnspr4 libnss3 libx11-xcb1';
            $deps[] = 'libxcomposite1 libxdamage1 libxrandr2 xdg-utils';
        }

        return $deps;
    }

    /**
     * Detect the database type from .env or composer.json.
     */
    protected function getDatabaseType(): string
    {
        if ($this->databaseType !== null) {
            return $this->databaseType;
        }

        // Check .env first (use last occurrence in case of duplicates)
        $envPath = base_path('.env');
        if (File::exists($envPath)) {
            $envContent = File::get($envPath);
            if (preg_match_all('/^DB_CONNECTION\s*=\s*(\w+)/m', $envContent, $matches)) {
                $connection = end($matches[1]); // Get last match
                if (in_array($connection, ['pgsql', 'postgres', 'postgresql'])) {
                    $this->databaseType = 'pgsql';

                    return $this->databaseType;
                }
            }
        }

        // Default to MySQL
        $this->databaseType = 'mysql';

        return $this->databaseType;
    }

    /**
     * Check if Browsershot is installed.
     */
    protected function hasBrowsershot(): bool
    {
        if ($this->hasBrowsershot !== null) {
            return $this->hasBrowsershot;
        }

        $this->hasBrowsershot = class_exists(\Spatie\Browsershot\Browsershot::class);

        return $this->hasBrowsershot;
    }

    /**
     * Check if the project has Node dependencies.
     */
    protected function hasNodeDependencies(): bool
    {
        if ($this->hasNode === null) {
            $this->hasNode = File::exists(base_path('package.json'));
        }

        return $this->hasNode;
    }

    /**
     * Get php-fpm supervisor program.
     */
    protected function getPhpFpmProgram(): string
    {
        return <<<'CONF'
; ===================
; PHP-FPM
; ===================
[program:php-fpm]
command=/usr/local/sbin/php-fpm --nodaemonize
autostart=true
autorestart=true
priority=5
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
CONF;
    }

    /**
     * Get nginx supervisor program.
     */
    protected function getNginxProgram(): string
    {
        return <<<'CONF'
; ===================
; Nginx
; ===================
[program:nginx]
command=/usr/sbin/nginx
autostart=true
autorestart=true
priority=10
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
CONF;
    }

    /**
     * Convert a detector's supervisor config to Docker format.
     */
    protected function getDockerSupervisorProgram(PackageDetector $detector): ?string
    {
        $name = strtolower($detector->name());

        if (str_contains($name, 'horizon')) {
            return <<<'CONF'
; ===================
; Laravel Horizon
; ===================
[program:horizon]
command=/usr/local/bin/php /var/www/html/artisan horizon
user=www-data
autostart=true
autorestart=true
priority=15
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
stopwaitsecs=3600
CONF;
        }

        if (str_contains($name, 'scheduler')) {
            return <<<'CONF'
; ===================
; Laravel Scheduler
; ===================
[program:scheduler]
command=/bin/sh -c "while true; do /usr/local/bin/php /var/www/html/artisan schedule:run --verbose --no-interaction; sleep 60; done"
user=www-data
autostart=true
autorestart=true
priority=20
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
CONF;
        }

        if (str_contains($name, 'reverb')) {
            return <<<'CONF'
; ===================
; Laravel Reverb (WebSockets)
; ===================
[program:reverb]
command=/usr/local/bin/php /var/www/html/artisan reverb:start --host=0.0.0.0 --port=6001
user=www-data
autostart=true
autorestart=true
priority=25
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
CONF;
        }

        return null;
    }

    /**
     * Convert a detector's nginx locations to Docker format.
     *
     * @return array<string>
     */
    protected function getDockerNginxLocations(PackageDetector $detector): array
    {
        $name = strtolower($detector->name());

        if (str_contains($name, 'reverb')) {
            return [
                <<<'NGINX'
        # Reverb WebSocket proxy
        location /app {
            proxy_pass http://127.0.0.1:6001;
            proxy_http_version 1.1;
            proxy_set_header Upgrade $http_upgrade;
            proxy_set_header Connection "upgrade";
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;
            proxy_read_timeout 60s;
            proxy_send_timeout 60s;
        }
NGINX,
            ];
        }

        return [];
    }

    /**
     * Get a summary of what will be generated.
     *
     * @return array{packages: array<string>, workers: array<string>, php_extensions: array<string>, database: string, has_browsershot: bool}
     */
    public function getSummary(): array
    {
        $workers = ['php-fpm', 'nginx'];

        foreach ($this->detected as $detector) {
            $workers[] = strtolower($detector->name());
        }

        return [
            'packages' => array_map(fn ($d) => $d->name(), $this->detected),
            'workers' => $workers,
            'php_extensions' => $this->getPhpExtensions(),
            'database' => $this->getDatabaseType(),
            'has_browsershot' => $this->hasBrowsershot(),
        ];
    }
}
