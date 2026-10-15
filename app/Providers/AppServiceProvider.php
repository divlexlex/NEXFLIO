<?php

namespace App\Providers;

use App\Models\Notification;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
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

        // Navbar bell (Design C, chunk 1): every render of the public nav
        // carries the signed-in client's latest notifications + unread count
        // under $nav* names, so they never collide with the dashboard view's
        // own $notifications. Guests / non-clients get an empty collection
        // and 0 so the partial never errors.
        View::composer('partials.nav', function ($view) {
            if (auth()->check() && auth()->user()->isClient()) {
                $view->with('navNotifications', Notification::where('user_id', auth()->id())->latest()->take(6)->get());
                $view->with('navUnreadCount', Notification::where('user_id', auth()->id())->whereNull('read_at')->count());
            } else {
                $view->with('navNotifications', collect());
                $view->with('navUnreadCount', 0);
            }
        });
    }
}
