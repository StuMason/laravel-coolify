<?php

namespace Stumason\Coolify\Console;

use Illuminate\Console\Command;
use Stumason\Coolify\Contracts\ApplicationRepository;
use Stumason\Coolify\Coolify;
use Stumason\Coolify\CoolifyClient;
use Stumason\Coolify\Exceptions\CoolifyApiException;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\spin;

#[AsCommand(name: 'coolify:restart')]
class RestartCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coolify:restart
                            {--uuid= : Application UUID (defaults to config)}
                            {--force : Restart without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restart your application on Coolify';

    /**
     * Execute the console command.
     */
    public function handle(CoolifyClient $client, ApplicationRepository $applications): int
    {
        if (! $client->isConfigured()) {
            $this->components->error('Coolify is not configured. Please set COOLIFY_URL and COOLIFY_TOKEN in your .env file.');

            return self::FAILURE;
        }

        $uuid = $this->option('uuid') ?? Coolify::getApplicationUuid();

        if (! $uuid) {
            $this->components->error('No application configured. Run coolify:provision first or use --uuid option.');

            return self::FAILURE;
        }

        // Confirm restart
        if (! $this->option('force') && ! $this->option('no-interaction')) {
            try {
                $app = $applications->get($uuid);
                $appName = $app['name'] ?? $uuid;

                if (! confirm("Restart {$appName}?", default: true)) {
                    $this->components->warn('Restart cancelled.');

                    return self::SUCCESS;
                }
            } catch (CoolifyApiException $e) {
                $this->components->error("Failed to fetch application: {$e->getMessage()}");

                return self::FAILURE;
            }
        }

        // Trigger restart
        try {
            spin(
                callback: fn () => $applications->restart($uuid),
                message: 'Restarting application...'
            );

            $this->components->info('Application restart triggered successfully!');

        } catch (CoolifyApiException $e) {
            $this->components->error("Restart failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
