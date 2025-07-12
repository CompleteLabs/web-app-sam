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
    /**
     * Bootstrap any application services.
     *
     * LogViewer Configuration:
     * - URL Path: 'log-viewer' (fixed path)
     * - Access: SUPER ADMIN role only
     * - Gate: viewLogViewer
     */
    public function boot(): void
    {
        Gate::define('viewLogViewer', function ($user = null) {
            // Only allow SUPER ADMIN to access log viewer
            return $user && $user->role && $user->role->name === 'SUPER ADMIN';
        });
    }
}
