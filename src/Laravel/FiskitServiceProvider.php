<?php

declare(strict_types=1);

namespace Fiskit\Laravel;

use Fiskit\Fiskit;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the client in Laravel, read from config/fiskit.php (FISKIT_API_KEY in .env). Discovered
 * automatically: nothing to add to config/app.php.
 */
final class FiskitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/fiskit.php', 'fiskit');

        $this->app->singleton(Fiskit::class, function ($app): Fiskit {
            $config = $app['config']['fiskit'];
            return new Fiskit($config['api_key'] ?? null, array_filter([
                'base_url' => $config['base_url'] ?? null,
                'max_retries' => $config['max_retries'] ?? null,
                'timeout' => $config['timeout'] ?? null,
            ], static fn ($value) => $value !== null));
        });
        $this->app->alias(Fiskit::class, 'fiskit');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__ . '/../../config/fiskit.php' => config_path('fiskit.php')], 'fiskit-config');
        }
    }
}
