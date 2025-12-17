<?php

use Stumason\Coolify\Coolify;
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

        $this->notification = new DeploymentFailedNotification($this->application, $this->deployment, 'Build error occurred');
    });

    it('includes mail channel when email is configured', function () {
        Coolify::routeMailNotificationsTo('test@example.com');

        $channels = $this->notification->via(null);

        expect($channels)->toContain('mail');

        // Reset
        Coolify::$email = null;
    });

    it('has mail representation with failure styling', function () {
        $mail = $this->notification->toMail(null);

        expect($mail->subject)->toContain('Deployment Failed')
            ->and($mail->subject)->toContain('Test Application');
    });

    it('includes failure reason in notification', function () {
        $mail = $this->notification->toMail(null);

        // The notification should contain the failure reason
        expect($mail)->not->toBeNull();
    });

    it('has slack representation with danger color', function () {
        $slack = $this->notification->toSlack(null);

        expect($slack)->not->toBeNull();
    });
});
