<?php

namespace Stumason\Coolify\Console;

use Illuminate\Console\Command;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'coolify:install')]
class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coolify:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install all of the Coolify resources';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->components->info('Installing Coolify resources.');

        collect([
            'Service Provider' => fn () => $this->callSilent('vendor:publish', ['--tag' => 'coolify-provider']) == 0,
            'Configuration' => fn () => $this->callSilent('vendor:publish', ['--tag' => 'coolify-config']) == 0,
        ])->each(fn ($task, $description) => $this->components->task($description, $task));

        $this->registerCoolifyServiceProvider();

        $this->components->info('Coolify scaffolding installed successfully.');
        $this->newLine();

        $this->components->bulletList([
            'Add your Coolify API token to .env: <comment>COOLIFY_TOKEN=your-token</comment>',
            'Set your Coolify URL if self-hosted: <comment>COOLIFY_URL=https://coolify.example.com</comment>',
            'Run <comment>php artisan coolify:status --all</comment> to test the connection',
            'Run <comment>php artisan coolify:provision</comment> to set up your infrastructure',
        ]);

        return self::SUCCESS;
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
