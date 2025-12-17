<?php

namespace Stumason\Coolify\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;
use Stumason\Coolify\Coolify;
use Stumason\Coolify\Events\DeploymentFailed as DeploymentFailedEvent;

class DeploymentFailed extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public DeploymentFailedEvent $event
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
        $reason = $this->event->reason();

        return (new SlackMessage)
            ->error()
            ->content("Deployment failed for {$applicationName}")
            ->attachment(function ($attachment) use ($applicationName, $deploymentUuid, $reason) {
                $attachment
                    ->title('Deployment Failed')
                    ->fields([
                        'Application' => $applicationName,
                        'Deployment ID' => $deploymentUuid,
                        'Status' => 'Failed',
                        'Reason' => $reason,
                    ]);
            });
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $applicationName = $this->event->applicationName();
        $reason = $this->event->reason();

        return (new MailMessage)
            ->subject("Deployment Failed: {$applicationName}")
            ->error()
            ->line("The deployment for {$applicationName} has failed.")
            ->line("Deployment ID: {$this->event->deploymentUuid()}")
            ->line("Reason: {$reason}")
            ->line('Please check your Coolify dashboard for more details.');
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
            'status' => 'failed',
            'reason' => $this->event->reason(),
        ];
    }
}
