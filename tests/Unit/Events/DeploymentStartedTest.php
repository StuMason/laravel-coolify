<?php

use Stumason\Coolify\Events\DeploymentStarted;

describe('DeploymentStarted', function () {
    beforeEach(function () {
        $this->application = [
            'uuid' => 'app-uuid-123',
            'name' => 'Test Application',
        ];

        $this->deployment = [
            'uuid' => 'deployment-uuid-456',
            'deployment_uuid' => 'deployment-uuid-456',
            'status' => 'in_progress',
        ];

        $this->event = new DeploymentStarted($this->application, $this->deployment);
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

    it('handles missing uuid gracefully', function () {
        $event = new DeploymentStarted([], []);

        expect($event->applicationUuid())->toBe('')
            ->and($event->deploymentUuid())->toBe('')
            ->and($event->applicationName())->toBe('Unknown');
    });
});
