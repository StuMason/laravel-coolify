<?php

declare(strict_types=1);

namespace Stumason\Coolify\Console;

use Illuminate\Console\Command;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Stumason\Coolify\Nixpacks\NixpacksGenerator;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

#[AsCommand(name: 'coolify:install')]
class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coolify:install
                            {--force : Overwrite existing files}
                            {--no-nixpacks : Skip nixpacks.toml generation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install Laravel Coolify: publish config and generate nixpacks.toml';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        info('Installing Laravel Coolify...');
        $this->newLine();

        // Publish config and service provider
        collect([
            'Service Provider' => fn () => $this->callSilent('vendor:publish', [
                '--tag' => 'coolify-provider',
                '--force' => $this->option('force'),
            ]) == 0,
            'Configuration' => fn () => $this->callSilent('vendor:publish', [
                '--tag' => 'coolify-config',
                '--force' => $this->option('force'),
            ]) == 0,
        ])->each(fn ($task, $description) => $this->components->task($description, $task));

        $this->registerCoolifyServiceProvider();
        $this->configureTrustedProxies();

        // Generate nixpacks.toml
        if (! $this->option('no-nixpacks')) {
            $this->newLine();
            $this->generateNixpacks();
        }

        // Summary
        $this->newLine();
        info('Laravel Coolify installed successfully!');
        $this->newLine();

        // Check if .env is configured
        if ($this->isConfigured()) {
            $this->call('coolify:status', ['--all' => true]);
            $this->newLine();
            info('Run `php artisan coolify:provision` to set up your infrastructure.');
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
     * Generate nixpacks.toml based on detected packages.
     */
    protected function generateNixpacks(): void
    {
        $generator = new NixpacksGenerator;

        // Handle existing file - early returns reduce nesting
        if ($generator->exists() && ! $this->option('force')) {
            if ($this->option('no-interaction')) {
                warning('nixpacks.toml already exists. Use --force to overwrite.');

                return;
            }

            if (! confirm(label: 'nixpacks.toml already exists. Overwrite?', default: false)) {
                warning('Skipping nixpacks.toml generation.');

                return;
            }
        }

        $this->line('  <fg=cyan>Generating nixpacks.toml...</>');
        $this->newLine();

        // Run detection
        $detected = $generator->detect();

        if (empty($detected)) {
            $this->line('  No additional packages detected (base Laravel app).');
        } else {
            $this->line('  <fg=green>Detected packages:</>');
            foreach ($detected as $detector) {
                $this->line("    <fg=white>✓</> {$detector->name()}");
            }
        }

        $this->newLine();

        // Generate and write
        $path = $generator->write();
        $summary = $generator->getSummary();

        $this->line('  <fg=green>Generated nixpacks.toml with:</>');

        $this->line('  <fg=cyan>Supervisor workers:</>');
        foreach ($summary['workers'] as $worker) {
            $this->line("    <fg=white>•</> {$worker}");
        }

        if (! empty($summary['php_extensions'])) {
            $this->newLine();
            $this->line('  <fg=cyan>PHP extensions:</>');
            foreach ($summary['php_extensions'] as $extension) {
                $this->line("    <fg=white>•</> {$extension}");
            }
        }

        $this->newLine();
        $this->components->task('nixpacks.toml', fn () => true);
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

        $providerPath = app_path('Providers/CoolifyServiceProvider.php');
        if (file_exists($providerPath)) {
            file_put_contents($providerPath, str_replace(
                'namespace App\Providers;',
                "namespace {$namespace}\Providers;",
                file_get_contents($providerPath)
            ));
        }
    }

    /**
     * Configure TrustProxies middleware in bootstrap/app.php for Coolify's reverse proxy.
     */
    protected function configureTrustedProxies(): void
    {
        $appPath = $this->laravel->bootstrapPath('app.php');

        if (! file_exists($appPath)) {
            return;
        }

        $content = file_get_contents($appPath);

        // Already configured
        if (Str::contains($content, 'trustProxies')) {
            return;
        }

        // Find the withMiddleware callback and add trustProxies
        // Pattern: ->withMiddleware(function (Middleware $middleware) {
        $pattern = '/(->withMiddleware\s*\(\s*function\s*\(\s*Middleware\s+\$middleware\s*\)\s*\{)/';

        if (preg_match($pattern, $content)) {
            $replacement = '$1'.PHP_EOL.'        $middleware->trustProxies(at: \'*\');';
            $content = preg_replace($pattern, $replacement, $content);
            file_put_contents($appPath, $content);

            $this->components->task('Configure TrustProxies', fn () => true);
        }
    }
}
