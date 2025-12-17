<?php

namespace Stumason\Coolify\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApplicationRestarted
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  array<string, mixed>  $application
     */
    public function __construct(
        public array $application
    ) {}

    /**
     * Get the application UUID.
     */
    public function applicationUuid(): string
    {
        return $this->application['uuid'] ?? '';
    }

    /**
     * Get the application name.
     */
    public function applicationName(): string
    {
        return $this->application['name'] ?? 'Unknown';
    }
}
