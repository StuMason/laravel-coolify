<?php

use Illuminate\Support\Facades\Http;
use Stumason\Coolify\Coolify;

beforeEach(function () {
    Http::preventStrayRequests();
    Coolify::auth(fn () => true);
});

describe('dashboard route constraints', function () {
    it('rejects encoded path segments in resource uuids', function (string $method, string $uri) {
        Http::fake();

        $this->json($method, $uri)->assertNotFound();

        Http::assertNothingSent();
    })->with([
        'env uuid dot-dot' => ['DELETE', '/coolify/api/applications/abc123/envs/%2e%2e'],
        'backup uuid dot-dot' => ['DELETE', '/coolify/api/databases/abc123/backups/%2e%2e'],
        'app uuid fragment' => ['POST', '/coolify/api/applications/abc123%23/restart'],
        'app uuid query' => ['POST', '/coolify/api/applications/abc123%3Fx=1/stop'],
        'kick app uuid dot-dot' => ['GET', '/coolify/api/kick/%2e%2e/status'],
    ]);
});
