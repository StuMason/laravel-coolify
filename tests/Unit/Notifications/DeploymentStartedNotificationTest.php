<?php

use Stumason\Coolify\Coolify;
use Stumason\Coolify\Notifications\DeploymentStarted as DeploymentStartedNotification;

describe('DeploymentStartedNotification', function () {
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

        $this->notification = new DeploymentStartedNotification($this->application, $this->deployment);
    });

    it('includes mail channel when email is configured', function () {
        Coolify::routeMailNotificationsTo('test@example.com');

        $channels = $this->notification->via(null);

        expect($channels)->toContain('mail');

        // Reset
        Coolify::$email = null;
    });

    it('includes slack channel when webhook is configured', function () {
        Coolify::routeSlackNotificationsTo('https://hooks.slack.com/test');

        $channels = $this->notification->via(null);

        expect($channels)->toContain('slack');

        // Reset
        Coolify::$slackWebhookUrl = null;
    });

    it('excludes mail channel when not configured', function () {
        Coolify::$email = null;

        $channels = $this->notification->via(null);

        expect($channels)->not->toContain('mail');
    });

    it('has mail representation', function () {
        $mail = $this->notification->toMail(null);

        expect($mail->subject)->toContain('Deployment Started')
            ->and($mail->subject)->toContain('Test Application');
    });

    it('has slack representation', function () {
        $slack = $this->notification->toSlack(null);

        expect($slack)->not->toBeNull();
    });
});
