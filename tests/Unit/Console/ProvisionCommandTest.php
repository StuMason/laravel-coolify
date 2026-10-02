<?php

use Illuminate\Support\Facades\File;
use Stumason\Coolify\Console\ProvisionCommand;

describe('ProvisionCommand::postgresDatabaseName', function () {
    it('produces names Coolify accepts as postgres_db', function (string $appName, string $expected) {
        $name = ProvisionCommand::postgresDatabaseName($appName);

        expect($name)
            ->toBe($expected)
            ->toMatch('/^[A-Za-z_][A-Za-z0-9_]{0,62}$/');
    })->with([
        'plain' => ['appfirst', 'appfirst'],
        'hyphen' => ['app-first', 'app_first'],
        'space and capitals' => ['My App', 'my_app'],
        'non-ascii' => ['Café', 'cafe'],
        'leading digit' => ['2fa-app', '_2fa_app'],
        'too long' => [str_repeat('a', 70), str_repeat('a', 63)],
    ]);
});

describe('ProvisionCommand::updateEnvFile', function () {
    it('keeps the original .env permissions', function () {
        $envPath = base_path('.env');
        $original = File::exists($envPath) ? File::get($envPath) : null;

        try {
            File::put($envPath, "APP_NAME=Test\n");
            chmod($envPath, 0600);

            $update = Closure::bind(fn (string $key, string $value) => $this->updateEnvFile($key, $value), new ProvisionCommand, ProvisionCommand::class);
            $update('COOLIFY_PROJECT_UUID', 'abc123');

            clearstatcache();
            expect(fileperms($envPath) & 0777)->toBe(0600)
                ->and(File::get($envPath))->toContain('COOLIFY_PROJECT_UUID=abc123');
        } finally {
            $original === null ? File::delete($envPath) : File::put($envPath, $original);
        }
    });
});

describe('ProvisionCommand::generateSshKeyPair', function () {
    it('generates a keypair and leaves nothing in the temp dir', function () {
        $before = glob(sys_get_temp_dir().'/coolify-key-*') ?: [];

        $generate = Closure::bind(fn (string $name) => $this->generateSshKeyPair($name), new ProvisionCommand, ProvisionCommand::class);
        $keyPair = $generate('test-key');

        expect($keyPair['private_key'])->toContain('PRIVATE KEY')
            ->and($keyPair['public_key'])->toStartWith('ssh-ed25519 ')
            ->and(glob(sys_get_temp_dir().'/coolify-key-*') ?: [])->toBe($before);
    })->skip(fn () => trim((string) shell_exec('command -v ssh-keygen')) === '', 'ssh-keygen not installed');
});
