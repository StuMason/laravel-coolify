<?php

use Stumason\Coolify\Events\DeploymentSucceeded;

describe('DeploymentSucceeded', function () {
    beforeEach(function () {
        $this->application = [
            'uuid' => 'app-uuid-123',
            'name' => 'Test Application',
        ];

        $this->deployment = [
            'uuid' => 'deployment-uuid-456',
            'deployment_uuid' => 'deployment-uuid-456',
            'status' => 'finished',
            'started_at' => '2024-01-01 10:00:00',
            'finished_at' => '2024-01-01 10:05:30',
        ];

        $this->event = new DeploymentSucceeded($this->application, $this->deployment);
    });

    it('stores application and deployment data', function () {
        expect($this->event->application)->toBe($this->application)
            ->and($this->event->deployment)->toBe($this->deployment);
    });

    it('returns application uuid', function () {
        expect($this->event->applicationUuid())->toBe('app-uuid-123');
    });

    it('returns deployment uuid', function () {
        expect($this->event->deploymentUuid())->toBe('deployment-uuid-456');
    });

    it('returns application name', function () {
        expect($this->event->applicationName())->toBe('Test Application');
    });

    it('calculates deployment duration', function () {
        expect($this->event->duration())->toBe(330); // 5 minutes 30 seconds
    });

    it('returns null duration when timestamps are missing', function () {
        $event = new DeploymentSucceeded($this->application, []);

        expect($event->duration())->toBeNull();
    });
});
