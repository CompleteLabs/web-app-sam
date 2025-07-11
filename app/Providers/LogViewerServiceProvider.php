<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;

class LogViewerServiceProvider extends ServiceProvider
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
        Gate::define('viewLogViewer', function ($user = null) {
            // Only allow SUPER ADMIN to access log viewer
            return Auth::user()?->role?->name === 'SUPER ADMIN';
        });
    }
}
