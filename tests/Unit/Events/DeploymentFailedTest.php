<?php

use Stumason\Coolify\Events\DeploymentFailed;

describe('DeploymentFailed', function () {
    beforeEach(function () {
        $this->application = [
            'uuid' => 'app-uuid-123',
            'name' => 'Test Application',
        ];

        $this->deployment = [
            'uuid' => 'deployment-uuid-456',
            'deployment_uuid' => 'deployment-uuid-456',
            'status' => 'failed',
            'status_message' => 'Build failed due to syntax error',
        ];

        $this->event = new DeploymentFailed($this->application, $this->deployment);
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

    it('returns failure reason from deployment status message', function () {
        expect($this->event->reason())->toBe('Build failed due to syntax error');
    });

    it('returns exception message when exception is provided', function () {
        $exception = new Exception('Connection timeout');
        $event = new DeploymentFailed($this->application, $this->deployment, $exception);

        expect($event->reason())->toBe('Connection timeout');
    });

    it('returns default message when no reason available', function () {
        $event = new DeploymentFailed($this->application, []);

        expect($event->reason())->toBe('Unknown error');
    });
});
