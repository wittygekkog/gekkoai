<?php

declare(strict_types=1);

namespace WittyGekko\SbuConnector;

use Illuminate\Support\ServiceProvider;
use WittyGekko\SbuConnector\Registry\OperationRegistry;
use WittyGekko\SbuConnector\Security\RequestSigner;
use WittyGekko\SbuConnector\Services\IdempotencyStore;
use WittyGekko\SbuConnector\Services\OperationDispatcher;

final class SbuConnectorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sbu-connector.php', 'sbu-connector');
        $this->app->singleton(OperationRegistry::class);
        $this->app->singleton(RequestSigner::class);
        $this->app->singleton(IdempotencyStore::class);
        $this->app->singleton(OperationDispatcher::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->publishes([
            __DIR__.'/../config/sbu-connector.php' => config_path('sbu-connector.php'),
        ], 'sbu-connector-config');
    }
}
