<?php

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
