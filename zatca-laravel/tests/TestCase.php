<?php

namespace KhaledHajSalem\ZatcaLaravel\Tests;

use KhaledHajSalem\ZatcaLaravel\ZatcaServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ZatcaServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Zatca' => \KhaledHajSalem\ZatcaLaravel\Facades\Zatca::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('zatca.environment', 'sandbox');
        $app['config']->set('zatca.seller.registration_name', 'Test Company');
        $app['config']->set('zatca.seller.vat_number', '399999999900003');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
