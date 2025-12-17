<?php

use Illuminate\Support\Facades\Http;
use Stumason\Coolify\Contracts\ServiceRepository;

describe('ServiceRepository', function () {
    beforeEach(function () {
        $this->repository = app(ServiceRepository::class);
    });

    it('lists all services', function () {
        Http::fake([
            '*/services' => Http::response([
                ['uuid' => 'service-1', 'name' => 'Redis', 'type' => 'redis', 'status' => 'running'],
                ['uuid' => 'service-2', 'name' => 'Minio', 'type' => 'minio', 'status' => 'stopped'],
            ]),
        ]);

        $services = $this->repository->all();

        expect($services)->toBeArray()
            ->and($services)->toHaveCount(2)
            ->and($services[0]['name'])->toBe('Redis');
    });

    it('gets a service by uuid', function () {
        Http::fake([
            '*/services/service-uuid-123' => Http::response([
                'uuid' => 'service-uuid-123',
                'name' => 'Redis Service',
                'type' => 'redis',
                'status' => 'running',
            ]),
        ]);

        $service = $this->repository->get('service-uuid-123');

        expect($service)->toBeArray()
            ->and($service['uuid'])->toBe('service-uuid-123')
            ->and($service['name'])->toBe('Redis Service');
    });

    it('starts a service', function () {
        Http::fake([
            '*/services/service-uuid-123/start' => Http::response([
                'message' => 'Service started',
            ]),
        ]);

        $result = $this->repository->start('service-uuid-123');

        expect($result)->toBeArray()
            ->and($result['message'])->toBe('Service started');
    });

    it('stops a service', function () {
        Http::fake([
            '*/services/service-uuid-123/stop' => Http::response([
                'message' => 'Service stopped',
            ]),
        ]);

        $result = $this->repository->stop('service-uuid-123');

        expect($result)->toBeArray()
            ->and($result['message'])->toBe('Service stopped');
    });

    it('restarts a service', function () {
        Http::fake([
            '*/services/service-uuid-123/restart' => Http::response([
                'message' => 'Service restarted',
            ]),
        ]);

        $result = $this->repository->restart('service-uuid-123');

        expect($result)->toBeArray()
            ->and($result['message'])->toBe('Service restarted');
    });
});
