<?php

use Illuminate\Support\Facades\File;

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

describe('SetupCiCommand', function () {
    it('generates GitHub Actions workflow', function () {
        $this->artisan('coolify:setup-ci', ['--no-interaction' => true])
            ->assertSuccessful();

        expect(File::exists(base_path('.github/workflows/coolify-deploy.yml')))->toBeTrue();
    });

    it('uses custom branch', function () {
        $this->artisan('coolify:setup-ci', ['--branch' => 'production', '--no-interaction' => true])
            ->assertSuccessful();

        $content = File::get(base_path('.github/workflows/coolify-deploy.yml'));
        expect($content)->toContain('branches: [production]');
    });

    it('fails without force when file exists', function () {
        File::makeDirectory(base_path('.github/workflows'), 0755, true);
        File::put(base_path('.github/workflows/coolify-deploy.yml'), '# existing');

        $this->artisan('coolify:setup-ci', ['--no-interaction' => true])
            ->assertFailed();
    });

    it('overwrites with force flag', function () {
        File::makeDirectory(base_path('.github/workflows'), 0755, true);
        File::put(base_path('.github/workflows/coolify-deploy.yml'), '# existing');

        $this->artisan('coolify:setup-ci', ['--force' => true, '--no-interaction' => true])
            ->assertSuccessful();

        $content = File::get(base_path('.github/workflows/coolify-deploy.yml'));
        expect($content)->toContain('name: Deploy to Coolify');
    });

    it('shows success message', function () {
        $this->artisan('coolify:setup-ci', ['--no-interaction' => true])
            ->expectsOutputToContain('GitHub Actions workflow generated')
            ->assertSuccessful();
    });
});
