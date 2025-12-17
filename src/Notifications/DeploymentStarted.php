<?php

namespace Stumason\Coolify\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;
use Stumason\Coolify\Coolify;
use Stumason\Coolify\Events\DeploymentStarted as DeploymentStartedEvent;

class DeploymentStarted extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public DeploymentStartedEvent $event
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        if (Coolify::$slackWebhookUrl) {
            $channels[] = 'slack';
        }

        if (Coolify::$email) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the Slack representation of the notification.
     */
    public function toSlack(object $notifiable): SlackMessage
    {
        $applicationName = $this->event->applicationName();
        $deploymentUuid = $this->event->deploymentUuid();

        return (new SlackMessage)
            ->warning()
            ->content("Deployment started for {$applicationName}")
            ->attachment(function ($attachment) use ($applicationName, $deploymentUuid) {
                $attachment
                    ->title('Deployment Started')
                    ->fields([
                        'Application' => $applicationName,
                        'Deployment ID' => $deploymentUuid,
                        'Status' => 'In Progress',
                    ]);
            });
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $applicationName = $this->event->applicationName();

        return (new MailMessage)
            ->subject("Deployment Started: {$applicationName}")
            ->line("A new deployment has started for {$applicationName}.")
            ->line("Deployment ID: {$this->event->deploymentUuid()}")
            ->line('You will be notified when the deployment completes.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'application' => $this->event->applicationName(),
            'application_uuid' => $this->event->applicationUuid(),
            'deployment_uuid' => $this->event->deploymentUuid(),
            'status' => 'started',
        ];
    }
}
