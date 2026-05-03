<?php

declare(strict_types=1);

namespace Hamzi\Synapse;

use Hamzi\Synapse\Application\Services\DriverRegistry;
use Hamzi\Synapse\Application\Services\HardwareMessageService;
use Hamzi\Synapse\Application\Services\MessageRouter;
use Hamzi\Synapse\Infrastructure\Livewire\SynapseConnector;
use Hamzi\Synapse\Infrastructure\Livewire\SynapseStatus;
use Hamzi\Synapse\Infrastructure\Printing\BladeEscPosRenderer;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class SynapseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/synapse.php', 'synapse');

        $this->app->singleton(DriverRegistry::class, fn ($app) => new DriverRegistry($app));
        $this->app->singleton(MessageRouter::class, fn ($app) => new MessageRouter($app['events']));
        $this->app->singleton(HardwareMessageService::class, fn ($app) => new HardwareMessageService(
            $app->make(DriverRegistry::class),
            $app->make(MessageRouter::class),
        ));
        $this->app->singleton(BladeEscPosRenderer::class, fn ($app) => new BladeEscPosRenderer($app['view']));
        $this->app->singleton('synapse', fn ($app) => new SynapseManager(
            $app->make(DriverRegistry::class),
            $app->make(HardwareMessageService::class),
        ));
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'synapse');

        $this->publishes([
            __DIR__.'/../config/synapse.php' => config_path('synapse.php'),
        ], 'synapse-config');

        $this->publishes([
            __DIR__.'/../resources/js/synapse-serial.js' => public_path('vendor/synapse/synapse-serial.js'),
        ], 'synapse-assets');

        Blade::anonymousComponentPath(__DIR__.'/../resources/views/components', 'synapse');

        Livewire::component('synapse-connector', SynapseConnector::class);
        Livewire::component('synapse-status', SynapseStatus::class);
    }
}
