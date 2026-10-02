<?php

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
        'leading digit' => ['2fa-app', '_2fa_app'],
        'too long' => [str_repeat('a', 70), str_repeat('a', 63)],
    ]);
});
