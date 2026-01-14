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

describe('DockerGenerator', function () {
    it('generates Dockerfile with configurable PHP version', function () {
        config(['coolify.docker.php_version' => '8.3']);

        $generator = new DockerGenerator;
        $generator->detect();
        $content = $generator->generateDockerfile();

        expect($content)->toContain('FROM php:8.3-fpm-bookworm AS production');
    });

    it('defaults to PHP 8.4 when not configured', function () {
        config(['coolify.docker.php_version' => null]);

        $generator = new DockerGenerator;
        $generator->detect();
        $content = $generator->generateDockerfile();

        expect($content)->toContain('FROM php:8.4-fpm-bookworm AS production');
    });

    it('includes frontend build stage when package.json exists', function () {
        File::put(base_path('package.json'), '{}');

        $generator = new DockerGenerator;
        $generator->detect();
        $content = $generator->generateDockerfile();

        expect($content)->toContain('FROM node:20-alpine AS frontend-build');
        expect($content)->toContain('COPY --from=frontend-build /app/public/build ./public/build');
    });

    it('excludes frontend build stage when no package.json', function () {
        $generator = new DockerGenerator;
        $generator->detect();
        $content = $generator->generateDockerfile();

        expect($content)->not->toContain('FROM node:20-alpine AS frontend-build');
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

        expect($files)->toHaveKey('Dockerfile');
        expect($files)->toHaveKey('docker/supervisord.conf');
        expect($files)->toHaveKey('docker/nginx.conf');
        expect($files)->toHaveKey('docker/php.ini');
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

    it('includes redis PECL extension by default', function () {
        $generator = new DockerGenerator;
        $generator->detect();
        $content = $generator->generateDockerfile();

        expect($content)->toContain('pecl install redis');
        expect($content)->toContain('docker-php-ext-enable redis');
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

describe('DockerGenerator database detection', function () {
    it('detects PostgreSQL from .env', function () {
        $envPath = base_path('.env');
        $originalContent = File::exists($envPath) ? File::get($envPath) : null;

        File::put($envPath, "DB_CONNECTION=pgsql\n");

        $generator = new DockerGenerator;
        $generator->detect();
        $content = $generator->generateDockerfile();

        expect($content)->toContain('pdo_pgsql pgsql');

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
        $content = $generator->generateDockerfile();

        expect($content)->toContain('pdo_mysql');
        expect($content)->not->toContain('pdo_pgsql');

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
