<?php

namespace KhaledHajSalem\ZatcaLaravel;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use KhaledHajSalem\ZatcaLaravel\Commands\ComplianceCheckCommand;
use KhaledHajSalem\ZatcaLaravel\Commands\GenerateCsrCommand;
use KhaledHajSalem\ZatcaLaravel\Commands\InstallCommand;
use KhaledHajSalem\ZatcaLaravel\Commands\OnboardingCommand;
use KhaledHajSalem\ZatcaLaravel\Commands\PruneCommand;
use KhaledHajSalem\ZatcaLaravel\Commands\StatusCommand;
use KhaledHajSalem\ZatcaLaravel\Services\ApiLogService;
use KhaledHajSalem\ZatcaLaravel\Services\ChainService;

class ZatcaServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerCommands();
        $this->registerPublishing();

        if (! config('zatca.enabled')) {
            return;
        }

        Route::middlewareGroup('zatca', config('zatca.middleware', []));

        $this->registerRoutes();
        $this->registerResources();
        $this->registerMigrations();
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/zatca.php', 'zatca');

        $this->app->singleton(ChainService::class);
        $this->app->singleton(ApiLogService::class);

        $this->app->singleton(ZatcaLaravel::class, function ($app) {
            return new ZatcaLaravel(
                $app->make(ChainService::class),
                $app->make(ApiLogService::class),
            );
        });
    }

    protected function registerRoutes(): void
    {
        Route::group([
            'domain'     => config('zatca.domain', null),
            'prefix'     => config('zatca.path'),
            'middleware'  => 'zatca',
            'namespace'  => 'KhaledHajSalem\\ZatcaLaravel\\Http\\Controllers',
        ], function () {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        });

        Route::group([
            'domain'     => config('zatca.domain', null),
            'prefix'     => config('zatca.path') . '/api',
            'middleware'  => 'zatca',
            'namespace'  => 'KhaledHajSalem\\ZatcaLaravel\\Http\\Controllers',
        ], function () {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        });
    }

    protected function registerResources(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'zatca');
    }

    protected function registerMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                GenerateCsrCommand::class,
                OnboardingCommand::class,
                ComplianceCheckCommand::class,
                StatusCommand::class,
                PruneCommand::class,
            ]);
        }
    }

    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/zatca.php' => config_path('zatca.php'),
            ], 'zatca-config');

            $this->publishes([
                __DIR__ . '/../public' => public_path('vendor/zatca'),
            ], 'zatca-assets');

            $this->publishes([
                __DIR__ . '/../stubs/ZatcaServiceProvider.stub' => app_path('Providers/ZatcaServiceProvider.php'),
            ], 'zatca-provider');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'zatca-migrations');
        }
    }
}
