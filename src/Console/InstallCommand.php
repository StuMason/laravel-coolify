<?php

namespace Stumason\Coolify\Console;

use Illuminate\Console\Command;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

#[AsCommand(name: 'coolify-dashboard:install')]
class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coolify-dashboard:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install the Coolify dashboard for managing deployments';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        info('Installing Coolify Dashboard...');

        collect([
            'Service Provider' => fn () => $this->callSilent('vendor:publish', ['--tag' => 'coolify-provider']) == 0,
            'Configuration' => fn () => $this->callSilent('vendor:publish', ['--tag' => 'coolify-config']) == 0,
        ])->each(fn ($task, $description) => $this->components->task($description, $task));

        $this->registerCoolifyServiceProvider();

        info('Coolify Dashboard installed successfully.');
        $this->newLine();

        // Check if .env is configured
        if ($this->isConfigured()) {
            $this->promptForProvisioning();
            $this->promptForStatusCheck();
        } else {
            warning('Coolify is not configured yet.');
            $this->newLine();
            $this->components->bulletList([
                'Add your Coolify API token to .env: <comment>COOLIFY_TOKEN=your-token</comment>',
                'Set your Coolify URL if self-hosted: <comment>COOLIFY_URL=https://coolify.example.com</comment>',
            ]);
            $this->newLine();
            info('Once configured, run:');
            $this->components->bulletList([
                '<comment>php artisan coolify:provision</comment> to set up your infrastructure',
                '<comment>php artisan coolify:status --all</comment> to test the connection',
            ]);
        }

        return self::SUCCESS;
    }

    /**
     * Check if Coolify is configured in .env.
     */
    protected function isConfigured(): bool
    {
        $token = config('coolify.token');
        $url = config('coolify.url');

        return ! empty($token) && ! empty($url);
    }

    /**
     * Prompt to check Coolify status.
     */
    protected function promptForStatusCheck(): void
    {
        if (confirm('Would you like to check the Coolify connection status?', true)) {
            $this->call('coolify:status', ['--all' => true]);
            $this->newLine();
        }
    }

    /**
     * Prompt to provision infrastructure.
     */
    protected function promptForProvisioning(): void
    {
        if (confirm('Would you like to provision your infrastructure now?', false)) {
            $this->call('coolify:provision');
        }
    }

    /**
     * Register the Coolify service provider in the application configuration file.
     */
    protected function registerCoolifyServiceProvider(): void
    {
        $namespace = Str::replaceLast('\\', '', $this->laravel->getNamespace());

        if (file_exists($this->laravel->bootstrapPath('providers.php'))) {
            ServiceProvider::addProviderToBootstrapFile("{$namespace}\\Providers\\CoolifyServiceProvider");
        } else {
            $appConfig = file_get_contents(config_path('app.php'));

            if (Str::contains($appConfig, $namespace.'\\Providers\\CoolifyServiceProvider::class')) {
                return;
            }

            file_put_contents(config_path('app.php'), str_replace(
                "{$namespace}\\Providers\AppServiceProvider::class,".PHP_EOL,
                "{$namespace}\\Providers\AppServiceProvider::class,".PHP_EOL."        {$namespace}\Providers\CoolifyServiceProvider::class,".PHP_EOL,
                $appConfig
            ));
        }

        file_put_contents(app_path('Providers/CoolifyServiceProvider.php'), str_replace(
            'namespace App\Providers;',
            "namespace {$namespace}\Providers;",
            file_get_contents(app_path('Providers/CoolifyServiceProvider.php'))
        ));
    }
}
