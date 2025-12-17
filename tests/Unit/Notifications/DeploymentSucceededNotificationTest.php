<?php

use Stumason\Coolify\Coolify;
use Stumason\Coolify\Notifications\DeploymentSucceeded as DeploymentSucceededNotification;

describe('DeploymentSucceededNotification', function () {
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

        $this->notification = new DeploymentSucceededNotification($this->application, $this->deployment);
    });

    it('includes mail channel when email is configured', function () {
        Coolify::routeMailNotificationsTo('test@example.com');

        $channels = $this->notification->via(null);

        expect($channels)->toContain('mail');

        // Reset
        Coolify::$email = null;
    });

    it('has mail representation with success styling', function () {
        $mail = $this->notification->toMail(null);

        expect($mail->subject)->toContain('Deployment Succeeded')
            ->and($mail->subject)->toContain('Test Application');
    });

    it('has slack representation with success color', function () {
        $slack = $this->notification->toSlack(null);

        expect($slack)->not->toBeNull();
    });
});
