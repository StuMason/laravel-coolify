<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Stumason\Coolify\CoolifyClient;
use Stumason\Coolify\Exceptions\CoolifyApiException;
use Stumason\Coolify\Exceptions\CoolifyAuthenticationException;
use Stumason\Coolify\Exceptions\CoolifyNotFoundException;

beforeEach(function () {
    $this->client = new CoolifyClient(
        'https://coolify.test',
        'test-token',
        'team-1'
    );
});

describe('CoolifyClient', function () {
    it('builds correct API URL', function () {
        Http::fake([
            'coolify.test/api/v1/applications' => Http::response(['data' => []], 200),
        ]);

        $this->client->get('applications', cached: false);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://coolify.test/api/v1/applications';
        });
    });

    it('includes authorization header', function () {
        Http::fake([
            '*' => Http::response(['data' => []], 200),
        ]);

        $this->client->get('applications', cached: false);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer test-token');
        });
    });

    it('makes GET requests', function () {
        Http::fake([
            '*' => Http::response(['uuid' => 'abc-123', 'name' => 'My App'], 200),
        ]);

        $result = $this->client->get('applications/abc-123', cached: false);

        expect($result)->toBeArray()
            ->and($result['uuid'])->toBe('abc-123')
            ->and($result['name'])->toBe('My App');
    });

    it('makes POST requests', function () {
        Http::fake([
            '*' => Http::response(['deployment_uuid' => 'deploy-123'], 200),
        ]);

        $result = $this->client->post('applications/abc-123/deploy', ['force' => true]);

        expect($result['deployment_uuid'])->toBe('deploy-123');

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && $request['force'] === true;
        });
    });

    it('makes PATCH requests', function () {
        Http::fake([
            '*' => Http::response(['uuid' => 'abc-123'], 200),
        ]);

        $result = $this->client->patch('applications/abc-123', ['name' => 'Updated']);

        Http::assertSent(function ($request) {
            return $request->method() === 'PATCH'
                && $request['name'] === 'Updated';
        });
    });

    it('makes DELETE requests', function () {
        Http::fake([
            '*' => Http::response([], 200),
        ]);

        $this->client->delete('applications/abc-123');

        Http::assertSent(function ($request) {
            return $request->method() === 'DELETE';
        });
    });

    it('throws authentication exception on 401', function () {
        Http::fake([
            '*' => Http::response(['message' => 'Unauthenticated'], 401),
        ]);

        $this->client->get('applications', cached: false);
    })->throws(CoolifyAuthenticationException::class);

    it('throws not found exception on 404', function () {
        Http::fake([
            '*' => Http::response(['message' => 'Not found'], 404),
        ]);

        $this->client->get('applications/not-found', cached: false);
    })->throws(CoolifyNotFoundException::class);

    it('throws API exception on other errors', function () {
        Http::fake([
            '*' => Http::response(['message' => 'Server error'], 500),
        ]);

        $this->client->get('applications', cached: false);
    })->throws(CoolifyApiException::class);

    it('includes validation errors in the API exception message', function () {
        Http::fake([
            '*' => Http::response([
                'message' => 'Validation failed.',
                'errors' => [
                    'postgres_db' => ['The postgres_db may only contain letters, digits, and underscores, and must start with a letter or underscore.'],
                ],
            ], 422),
        ]);

        expect(fn () => $this->client->post('databases/postgresql', []))
            ->toThrow(
                CoolifyApiException::class,
                'Validation failed. (postgres_db: The postgres_db may only contain letters, digits, and underscores, and must start with a letter or underscore.)'
            );
    });

    it('reports when configured', function () {
        expect($this->client->isConfigured())->toBeTrue();

        $unconfigured = new CoolifyClient('https://coolify.test', null);
        expect($unconfigured->isConfigured())->toBeFalse();
    });

    it('tests connection successfully', function () {
        Http::fake([
            '*' => Http::response(['version' => '4.0.0'], 200),
        ]);

        expect($this->client->testConnection())->toBeTrue();
    });

    it('tests connection failure', function () {
        Http::fake([
            '*' => Http::response(['message' => 'Error'], 500),
        ]);

        expect($this->client->testConnection())->toBeFalse();
    });
});

describe('CoolifyClient caching', function () {
    // The suite-wide TestCase disables caching (cache_ttl = 0) — which is
    // exactly why the invalidation bug shipped. Re-enable it here.
    beforeEach(function () {
        config(['coolify.cache_ttl' => 30]);
        Cache::flush();
    });

    it('serves cached GETs within the TTL', function () {
        Http::fake([
            '*' => Http::response(['data' => ['v1']], 200),
        ]);

        $this->client->get('applications');
        $this->client->get('applications');

        Http::assertSentCount(1);
    });

    it('invalidates cached GETs after a mutation', function () {
        Http::fake([
            '*' => Http::response(['data' => []], 200),
        ]);

        $this->client->get('applications');
        $this->client->post('applications/abc-123/restart');
        $this->client->get('applications');

        // GET, POST, then a fresh GET — the cached copy must not survive
        // the mutation for the rest of the TTL.
        Http::assertSentCount(3);
    });

    it('clearCache never touches the host application cache', function () {
        Http::fake([
            '*' => Http::response(['data' => []], 200),
        ]);

        Cache::put('host-app-key', 'precious', 3600);
        $this->client->get('applications');

        $this->client->clearCache();

        expect(Cache::get('host-app-key'))->toBe('precious');
    });
});

describe('CoolifyClient retries', function () {
    it('retries failed GET requests', function () {
        Http::fake([
            '*' => Http::sequence()
                ->push(['message' => 'Error'], 500)
                ->push(['message' => 'Error'], 500)
                ->push(['data' => []], 200),
        ]);

        $result = $this->client->get('applications', cached: false);

        expect($result)->toBe(['data' => []]);
        Http::assertSentCount(3);
    });

    it('never retries mutations', function () {
        Http::fake([
            '*' => Http::response(['message' => 'Error'], 500),
        ]);

        // A timed-out or failed deploy may already have been acted on
        // server-side — retrying it would fire the deployment again.
        expect(fn () => $this->client->post('applications/abc-123/deploy'))
            ->toThrow(CoolifyApiException::class);

        Http::assertSentCount(1);
    });
});
