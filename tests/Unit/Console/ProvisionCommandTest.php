<?php

use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Http;
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
    it('updates existing vars by key on re-run and keeps the existing APP_KEY', function () {
        Http::fake([
            '*/applications/app-123/envs' => Http::sequence()
                ->push([
                    ['uuid' => 'env-key', 'key' => 'APP_KEY', 'value' => 'base64:existing'],
                    ['uuid' => 'env-name', 'key' => 'APP_NAME', 'value' => 'Old Name'],
                ])
                ->whenEmpty(Http::response(['uuid' => 'env-new'], 201)),
        ]);

        $command = new ProvisionCommand;
        $command->setLaravel(app());
        $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));

        (new ReflectionMethod($command, 'setApplicationEnvVars'))->invoke(
            $command,
            app(ApplicationRepository::class),
            'app-123',
            'project-123',
            null,
            null,
            app(DatabaseRepository::class),
            'server-123',
            'production',
            'key-123',
            'owner/repo',
            'main',
            'My App',
            'myapp.example.com',
        );

        $writes = Http::recorded(fn ($request) => $request->method() !== 'GET')
            ->map(fn ($pair) => $pair[0]);

        // APP_KEY already exists on Coolify: never sent, so it can't be rotated
        expect($writes->filter(fn ($request) => $request['key'] === 'APP_KEY'))->toBeEmpty();

        // Existing APP_NAME is updated via Coolify's key-matched PATCH route
        $nameUpdate = $writes->first(fn ($request) => $request['key'] === 'APP_NAME');
        expect($nameUpdate)->not->toBeNull()
            ->and($nameUpdate->method())->toBe('PATCH')
            ->and($nameUpdate->url())->toEndWith('applications/app-123/envs')
            ->and($nameUpdate['value'])->toBe('My App')
            ->and(isset($nameUpdate['uuid']))->toBeFalse();

        // Vars that don't exist yet are created
        expect($writes->first(fn ($request) => $request['key'] === 'APP_URL')?->method())->toBe('POST');
    });
});
