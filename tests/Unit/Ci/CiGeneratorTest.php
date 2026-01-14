<?php

use Illuminate\Support\Facades\File;
use Stumason\Coolify\Ci\CiGenerator;

beforeEach(function () {
    if (File::exists(base_path('.github/workflows/coolify-deploy.yml'))) {
        File::delete(base_path('.github/workflows/coolify-deploy.yml'));
    }
    if (File::isDirectory(base_path('.github'))) {
        File::deleteDirectory(base_path('.github'));
    }
});

afterEach(function () {
    if (File::exists(base_path('.github/workflows/coolify-deploy.yml'))) {
        File::delete(base_path('.github/workflows/coolify-deploy.yml'));
    }
    if (File::isDirectory(base_path('.github'))) {
        File::deleteDirectory(base_path('.github'));
    }
});

describe('CiGenerator', function () {
    it('generates GitHub Actions workflow', function () {
        $generator = new CiGenerator;
        $content = $generator->generate();

        expect($content)->toContain('name: Deploy to Coolify');
        expect($content)->toContain('branches: [main]');
        expect($content)->toContain('workflow_dispatch:');
        expect($content)->toContain('-X POST');
        expect($content)->toContain('COOLIFY_URL');
        expect($content)->toContain('COOLIFY_TOKEN');
        expect($content)->toContain('COOLIFY_APP_UUID');
    });

    it('uses custom branch', function () {
        $generator = new CiGenerator;
        $generator->branch('production');
        $content = $generator->generate();

        expect($content)->toContain('branches: [production]');
    });

    it('can disable manual trigger', function () {
        $generator = new CiGenerator;
        $generator->manualTrigger(false);
        $content = $generator->generate();

        expect($content)->not->toContain('workflow_dispatch:');
    });

    it('writes workflow to disk', function () {
        $generator = new CiGenerator;
        $filePath = $generator->write();

        expect(File::exists($filePath))->toBeTrue();
        expect($filePath)->toContain('.github/workflows/coolify-deploy.yml');
    });

    it('detects existing workflow', function () {
        $generator = new CiGenerator;

        expect($generator->exists())->toBeFalse();

        $generator->write();

        expect($generator->exists())->toBeTrue();
    });

    it('supports method chaining', function () {
        $generator = new CiGenerator;

        $result = $generator->branch('develop')->manualTrigger(false);

        expect($result)->toBeInstanceOf(CiGenerator::class);
    });
});
