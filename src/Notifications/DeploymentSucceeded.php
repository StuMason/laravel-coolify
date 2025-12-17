<?php

namespace Stumason\Coolify\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;
use Stumason\Coolify\Coolify;
use Stumason\Coolify\Events\DeploymentSucceeded as DeploymentSucceededEvent;

class DeploymentSucceeded extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public DeploymentSucceededEvent $event
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
        $duration = $this->event->duration();

        return (new SlackMessage)
            ->success()
            ->content("Deployment succeeded for {$applicationName}")
            ->attachment(function ($attachment) use ($applicationName, $deploymentUuid, $duration) {
                $fields = [
                    'Application' => $applicationName,
                    'Deployment ID' => $deploymentUuid,
                    'Status' => 'Success',
                ];

                if ($duration !== null) {
                    $fields['Duration'] = "{$duration} seconds";
                }

                $attachment
                    ->title('Deployment Succeeded')
                    ->fields($fields);
            });
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $applicationName = $this->event->applicationName();
        $duration = $this->event->duration();

        $message = (new MailMessage)
            ->subject("Deployment Succeeded: {$applicationName}")
            ->line("The deployment for {$applicationName} completed successfully.")
            ->line("Deployment ID: {$this->event->deploymentUuid()}");

        if ($duration !== null) {
            $message->line("Duration: {$duration} seconds");
        }

        return $message;
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
            'status' => 'succeeded',
            'duration' => $this->event->duration(),
        ];
    }
}
