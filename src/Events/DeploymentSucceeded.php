<?php

namespace Stumason\Coolify\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeploymentSucceeded
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  array<string, mixed>  $application
     * @param  array<string, mixed>  $deployment
     */
    public function __construct(
        public array $application,
        public array $deployment
    ) {}

    /**
     * Get the application UUID.
     */
    public function applicationUuid(): string
    {
        return $this->application['uuid'] ?? '';
    }

    /**
     * Get the deployment UUID.
     */
    public function deploymentUuid(): string
    {
        return $this->deployment['deployment_uuid'] ?? $this->deployment['uuid'] ?? '';
    }

    /**
     * Get the application name.
     */
    public function applicationName(): string
    {
        return $this->application['name'] ?? 'Unknown';
    }

    /**
     * Get the deployment duration in seconds.
     */
    public function duration(): ?int
    {
        if (! isset($this->deployment['started_at'], $this->deployment['finished_at'])) {
            return null;
        }

        return strtotime($this->deployment['finished_at']) - strtotime($this->deployment['started_at']);
    }
}
