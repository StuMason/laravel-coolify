<?php

use Illuminate\Support\Facades\File;
use Stumason\Coolify\Docker\DockerGenerator;

beforeEach(function () {
    // Clean up any generated files
    $files = [
        base_path('Dockerfile'),
        base_path('docker/supervisord.conf'),
        base_path('docker/nginx.conf'),
        base_path('docker/php.ini'),
        base_path('docker/entrypoint.sh'),
        base_path('package.json'),
    ];
    foreach ($files as $file) {
        if (File::exists($file)) {
            File::delete($file);
        }
    }
    if (File::isDirectory(base_path('docker'))) {
        File::deleteDirectory(base_path('docker'));
    }
});

afterEach(function () {
    // Clean up generated files
    $files = [
        base_path('Dockerfile'),
        base_path('docker/supervisord.conf'),
        base_path('docker/nginx.conf'),
        base_path('docker/php.ini'),
        base_path('docker/entrypoint.sh'),
        base_path('package.json'),
    ];
    foreach ($files as $file) {
        if (File::exists($file)) {
            File::delete($file);
        }
    }
    if (File::isDirectory(base_path('docker'))) {
        File::deleteDirectory(base_path('docker'));
    }
});

describe('DockerGenerator with base images (default)', function () {
    it('uses base image by default', function () {
        config(['coolify.docker.use_base_image' => true]);
        config(['coolify.docker.php_version' => '8.4']);

        $generator = new DockerGenerator;
        $generator->detect();
        $content = $generator->generateDockerfile();

        expect($content)->toContain('FROM ghcr.io/stumason/laravel-coolify-base:8.4 AS production');
        expect($content)->toContain('Using pre-built base image');
    });

    it('uses correct PHP version in base image tag', function () {
        config(['coolify.docker.use_base_image' => true]);
        config(['coolify.docker.php_version' => '8.3']);

        $generator = new DockerGenerator;
        $generator->detect();
        $content = $generator->generateDockerfile();

        expect($content)->toContain('FROM ghcr.io/stumason/laravel-coolify-base:8.3 AS production');
    });

    it('uses node variant when package.json exists', function () {
        config(['coolify.docker.use_base_image' => true]);
        config(['coolify.docker.php_version' => '8.4']);
        File::put(base_path('package.json'), '{}');

        $generator = new DockerGenerator;
        $generator->detect();
        $content = $generator->generateDockerfile();

        expect($content)->toContain('FROM ghcr.io/stumason/laravel-coolify-base:8.4-node AS production');
        expect($content)->toContain('FROM ghcr.io/stumason/laravel-coolify-base:8.4-node AS frontend-build');
    });

    it('does not install PHP extensions when using base image', function () {
        config(['coolify.docker.use_base_image' => true]);

        $generator = new DockerGenerator;
        $generator->detect();
        $content = $generator->generateDockerfile();

        expect($content)->not->toContain('docker-php-ext-install');
        expect($content)->not->toContain('pecl install');
        expect($content)->not->toContain('apt-get install');
    });

    it('includes entrypoint configuration env vars', function () {
        config(['coolify.docker.use_base_image' => true]);
        config(['coolify.docker.auto_migrate' => true]);
        config(['coolify.docker.db_wait_timeout' => 60]);

        $generator = new DockerGenerator;
        $generator->detect();
        $content = $generator->generateDockerfile();

        expect($content)->toContain('ENV AUTO_MIGRATE=true');
        expect($content)->toContain('DB_WAIT_TIMEOUT=60');
    });
});

describe('DockerGenerator from scratch', function () {
    it('builds from scratch when use_base_image is false', function () {
        config(['coolify.docker.use_base_image' => false]);
        config(['coolify.docker.php_version' => '8.4']);

        $generator = new DockerGenerator;
        $generator->detect();
        $content = $generator->generateDockerfile();

        expect($content)->toContain('FROM php:8.4-fpm-bookworm AS production');
        expect($content)->toContain('Building from scratch');
    });

    it('uses configurable PHP version when building from scratch', function () {
        config(['coolify.docker.use_base_image' => false]);
        config(['coolify.docker.php_version' => '8.3']);

        $generator = new DockerGenerator;
        $generator->detect();
        $content = $generator->generateDockerfile();

        expect($content)->toContain('FROM php:8.3-fpm-bookworm AS production');
    });

    it('includes PHP extension installation when building from scratch', function () {
        config(['coolify.docker.use_base_image' => false]);

        $generator = new DockerGenerator;
        $generator->detect();
        $content = $generator->generateDockerfile();

        expect($content)->toContain('docker-php-ext-install');
        expect($content)->toContain('pecl install redis');
        expect($content)->toContain('docker-php-ext-enable redis');
    });

    it('includes system dependencies when building from scratch', function () {
        config(['coolify.docker.use_base_image' => false]);

        $generator = new DockerGenerator;
        $generator->detect();
        $content = $generator->generateDockerfile();

        expect($content)->toContain('apt-get install');
        expect($content)->toContain('nginx supervisor curl');
    });
});

describe('DockerGenerator common features', function () {
    it('includes frontend build stage when package.json exists', function () {
        config(['coolify.docker.php_version' => '8.4']);
        File::put(base_path('package.json'), '{}');

        $generator = new DockerGenerator;
        $generator->detect();
        $content = $generator->generateDockerfile();

        expect($content)->toContain('FROM ghcr.io/stumason/laravel-coolify-base:8.4-node AS frontend-build');
        expect($content)->toContain('COPY --from=frontend-build /app/public/build ./public/build');
        // Verify PHP dependencies are installed for Vite plugins like Wayfinder
        expect($content)->toContain('composer install --no-dev --no-scripts --no-autoloader --prefer-dist');
    });

    it('excludes frontend build stage when no package.json', function () {
        $generator = new DockerGenerator;
        $generator->detect();
        $content = $generator->generateDockerfile();

        expect($content)->not->toContain('AS frontend-build');
        expect($content)->toContain('# No frontend build (no package.json detected)');
    });

    it('generates supervisord.conf with base workers', function () {
        $generator = new DockerGenerator;
        $generator->detect();
        $content = $generator->generateSupervisordConf();

        expect($content)->toContain('[program:php-fpm]');
        expect($content)->toContain('[program:nginx]');
    });

    it('generates nginx.conf with correct settings', function () {
        config(['coolify.docker.nginx.client_max_body_size' => '50M']);

        $generator = new DockerGenerator;
        $generator->detect();
        $content = $generator->generateNginxConf();

        expect($content)->toContain('client_max_body_size 50M');
        expect($content)->toContain('listen 8080');
        expect($content)->toContain('fastcgi_pass 127.0.0.1:9000');
    });

    it('generates php.ini with correct settings', function () {
        config(['coolify.docker.php.memory_limit' => '512M']);
        config(['coolify.docker.php.max_execution_time' => 120]);

        $generator = new DockerGenerator;
        $generator->detect();
        $content = $generator->generatePhpIni();

        expect($content)->toContain('memory_limit = 512M');
        expect($content)->toContain('max_execution_time = 120');
        expect($content)->toContain('opcache.enable = 1');
    });

    it('writes all Docker files to disk', function () {
        $generator = new DockerGenerator;
        $generator->detect();
        $files = $generator->write();

        expect(File::exists(base_path('Dockerfile')))->toBeTrue();
        expect(File::exists(base_path('docker/supervisord.conf')))->toBeTrue();
        expect(File::exists(base_path('docker/nginx.conf')))->toBeTrue();
        expect(File::exists(base_path('docker/php.ini')))->toBeTrue();
        expect(File::exists(base_path('docker/entrypoint.sh')))->toBeTrue();

        expect($files)->toHaveKey('Dockerfile');
        expect($files)->toHaveKey('docker/supervisord.conf');
        expect($files)->toHaveKey('docker/nginx.conf');
        expect($files)->toHaveKey('docker/php.ini');
        expect($files)->toHaveKey('docker/entrypoint.sh');
    });

    it('generates Dockerfile with entrypoint', function () {
        $generator = new DockerGenerator;
        $generator->detect();
        $content = $generator->generateDockerfile();

        expect($content)->toContain('COPY docker/entrypoint.sh /entrypoint.sh');
        expect($content)->toContain('RUN chmod +x /entrypoint.sh');
        expect($content)->toContain('ENTRYPOINT ["/entrypoint.sh"]');
    });

    it('detects when Dockerfile exists', function () {
        File::put(base_path('Dockerfile'), '# test');

        $generator = new DockerGenerator;

        expect($generator->exists())->toBeTrue();
    });

    it('reports Dockerfile does not exist when missing', function () {
        $generator = new DockerGenerator;

        expect($generator->exists())->toBeFalse();
    });

    it('returns correct summary', function () {
        $generator = new DockerGenerator;
        $generator->detect();
        $summary = $generator->getSummary();

        expect($summary)->toHaveKey('packages');
        expect($summary)->toHaveKey('workers');
        expect($summary)->toHaveKey('php_extensions');
        expect($summary)->toHaveKey('database');
        expect($summary)->toHaveKey('has_browsershot');

        expect($summary['workers'])->toContain('php-fpm');
        expect($summary['workers'])->toContain('nginx');
    });

    it('uses configurable health check path', function () {
        config(['coolify.docker.health_check_path' => '/health']);

        $generator = new DockerGenerator;
        $generator->detect();
        $content = $generator->generateDockerfile();

        expect($content)->toContain('curl -f http://localhost:8080/health');
    });
});

describe('DockerGenerator entrypoint', function () {
    it('generates entrypoint.sh with migrations and optimize', function () {
        $generator = new DockerGenerator;
        $generator->detect();
        $content = $generator->generateEntrypoint();

        expect($content)->toContain('#!/bin/bash');
        expect($content)->toContain('set -e');
        expect($content)->toContain('php artisan migrate --force');
        expect($content)->toContain('php artisan optimize');
        expect($content)->toContain('php artisan storage:link');
        expect($content)->toContain('exec /usr/bin/supervisord');
    });

    it('includes database connection wait with retry', function () {
        $generator = new DockerGenerator;
        $generator->detect();
        $content = $generator->generateEntrypoint();

        expect($content)->toContain('php artisan db:show');
        expect($content)->toContain('DB_WAIT_TIMEOUT');
        expect($content)->toContain('Waiting for database');
    });

    it('respects AUTO_MIGRATE environment variable', function () {
        $generator = new DockerGenerator;
        $generator->detect();
        $content = $generator->generateEntrypoint();

        expect($content)->toContain('AUTO_MIGRATE="${AUTO_MIGRATE:-true}"');
        expect($content)->toContain('if [ "$AUTO_MIGRATE" = "true" ]');
        expect($content)->toContain('Skipping migrations (AUTO_MIGRATE=false)');
    });
});

describe('DockerGenerator database detection', function () {
    it('detects PostgreSQL from .env for summary', function () {
        $envPath = base_path('.env');
        $originalContent = File::exists($envPath) ? File::get($envPath) : null;

        File::put($envPath, "DB_CONNECTION=pgsql\n");

        $generator = new DockerGenerator;
        $generator->detect();
        $summary = $generator->getSummary();

        expect($summary['database'])->toBe('pgsql');

        if ($originalContent !== null) {
            File::put($envPath, $originalContent);
        } else {
            File::delete($envPath);
        }
    });

    it('defaults to MySQL when not PostgreSQL', function () {
        $envPath = base_path('.env');
        $originalContent = File::exists($envPath) ? File::get($envPath) : null;

        File::put($envPath, "DB_CONNECTION=mysql\n");

        $generator = new DockerGenerator;
        $generator->detect();
        $summary = $generator->getSummary();

        expect($summary['database'])->toBe('mysql');

        if ($originalContent !== null) {
            File::put($envPath, $originalContent);
        } else {
            File::delete($envPath);
        }
    });

    it('installs correct DB extension when building from scratch', function () {
        config(['coolify.docker.use_base_image' => false]);
        $envPath = base_path('.env');
        $originalContent = File::exists($envPath) ? File::get($envPath) : null;

        File::put($envPath, "DB_CONNECTION=pgsql\n");

        $generator = new DockerGenerator;
        $generator->detect();
        $content = $generator->generateDockerfile();

        expect($content)->toContain('pdo_pgsql');
        expect($content)->toContain('pgsql');

        if ($originalContent !== null) {
            File::put($envPath, $originalContent);
        } else {
            File::delete($envPath);
        }
    });

    it('uses last DB_CONNECTION when multiple exist', function () {
        $envPath = base_path('.env');
        $originalContent = File::exists($envPath) ? File::get($envPath) : null;

        File::put($envPath, "DB_CONNECTION=sqlite\nDB_CONNECTION=pgsql\n");

        $generator = new DockerGenerator;
        $generator->detect();
        $summary = $generator->getSummary();

        expect($summary['database'])->toBe('pgsql');

        if ($originalContent !== null) {
            File::put($envPath, $originalContent);
        } else {
            File::delete($envPath);
        }
    });
});
