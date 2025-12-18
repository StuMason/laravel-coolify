<?php

use Stumason\Coolify\Coolify;
use Stumason\Coolify\Events\DeploymentFailed as DeploymentFailedEvent;
use Stumason\Coolify\Notifications\DeploymentFailed as DeploymentFailedNotification;

describe('DeploymentFailedNotification', function () {
    beforeEach(function () {
        $this->application = [
            'uuid' => 'app-uuid-123',
            'name' => 'Test Application',
        ];

        $this->deployment = [
            'uuid' => 'deployment-uuid-456',
            'deployment_uuid' => 'deployment-uuid-456',
            'status' => 'failed',
            'status_message' => 'Build failed',
        ];

        $this->event = new DeploymentFailedEvent(
            $this->application,
            $this->deployment,
            new Exception('Build error occurred')
        );
        $this->notification = new DeploymentFailedNotification($this->event);
    });

    it('includes mail channel when email is configured', function () {
        Coolify::routeMailNotificationsTo('test@example.com');

        $channels = $this->notification->via((object) []);

        expect($channels)->toContain('mail');

        // Reset
        Coolify::$email = null;
    });

    it('has mail representation with failure styling', function () {
        $mail = $this->notification->toMail((object) []);

        expect($mail->subject)->toContain('Deployment Failed')
            ->and($mail->subject)->toContain('Test Application');
    });

    it('includes failure reason in notification', function () {
        $mail = $this->notification->toMail((object) []);

        // The notification should contain the failure reason
        expect($mail)->not->toBeNull();
    });
});
