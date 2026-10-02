<?php

use Illuminate\Console\OutputStyle;
use Stumason\Coolify\Console\ProvisionCommand;
use Stumason\Coolify\Contracts\ApplicationRepository;
use Stumason\Coolify\Contracts\DatabaseRepository;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

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

describe('ProvisionCommand::setApplicationEnvVars', function () {
    it('never copies the operator token and keeps non-Vite vars out of the build', function () {
        config(['coolify.url' => 'https://coolify.test', 'coolify.token' => 'operator-token']);

        $sent = [];
        $applications = Mockery::mock(ApplicationRepository::class);
        $applications->shouldReceive('envs')->andReturn([]);
        $applications->shouldReceive('createEnv')->andReturnUsing(function ($uuid, $env) use (&$sent) {
            $sent[$env['key']] = $env;

            return ['uuid' => 'env'.count($sent)];
        });

        $command = new ProvisionCommand;
        $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));

        $method = new ReflectionMethod($command, 'setApplicationEnvVars');
        $method->invoke($command, $applications, 'app1', 'proj1', null, null, Mockery::mock(DatabaseRepository::class), 'srv1', 'production', 'key1', 'owner/repo', 'main', 'my-app', 'my-app.test');

        expect($sent)->not->toHaveKey('COOLIFY_TOKEN')
            ->and($sent['COOLIFY_URL']['value'])->toBe('https://coolify.test')
            ->and($sent['APP_KEY']['is_buildtime'])->toBeFalse()
            ->and($sent['APP_KEY']['is_runtime'])->toBeTrue()
            ->and(collect($sent)->every(fn ($env) => $env['is_buildtime'] === str_starts_with($env['key'], 'VITE_')))->toBeTrue();
    });
});
