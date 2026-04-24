<?php

namespace KhaledHajSalem\ZatcaLaravel;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ZatcaApplicationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->gate();
    }

    /**
     * Register the ZATCA dashboard gate.
     *
     * This gate determines who can access the ZATCA dashboard.
     * Override this method in your published service provider.
     */
    protected function gate(): void
    {
        Gate::define('viewZatcaDashboard', function ($user) {
            return in_array($user->email, [
                //
            ]);
        });
    }
}
