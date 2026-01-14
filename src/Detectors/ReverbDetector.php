<?php

declare(strict_types=1);

namespace Stumason\Coolify\Detectors;

class ReverbDetector implements PackageDetector
{
    public function name(): string
    {
        return 'Laravel Reverb';
    }

    public function isInstalled(): bool
    {
        return class_exists(\Laravel\Reverb\ReverbServiceProvider::class);
    }

    public function getSupervisorConfig(): ?string
    {
        return <<<'CONF'
[program:worker-reverb]
process_name=%(program_name)s
command=php /app/artisan reverb:start --host=127.0.0.1 --port=6001
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=1
startsecs=0
stdout_logfile=/var/log/worker-reverb.log
stderr_logfile=/var/log/worker-reverb.log
CONF;
    }

    public function getNginxLocationBlocks(): array
    {
        // WebSocket proxy for Reverb - proxies /app to internal Reverb server
        return [
            <<<'NGINX'
        location /app {
            proxy_pass http://127.0.0.1:6001;
            proxy_http_version 1.1;
            proxy_set_header Upgrade $http_upgrade;
            proxy_set_header Connection "upgrade";
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;
            proxy_read_timeout 60s;
            proxy_send_timeout 60s;
        }
NGINX,
        ];
    }

    public function getPhpExtensions(): array
    {
        return [];
    }
}
