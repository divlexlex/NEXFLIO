<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Audit logging is handled by the App\Traits\Auditable trait on each
        // business model, not by observers.

        // The admin portal is built on Bootstrap 5.
        Paginator::useBootstrapFive();
    }
}
