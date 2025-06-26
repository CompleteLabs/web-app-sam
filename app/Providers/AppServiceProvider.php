<?php

namespace App\Providers;

use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
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
        FilamentView::registerRenderHook(
            PanelsRenderHook::SIDEBAR_FOOTER,
            fn () => view('sidebar-nav-end')
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::TOPBAR_START,
            fn () => Blade::render('@livewire(\App\Livewire\TopBarStart::class)')
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE,
            fn () => view('filament.resources.pages.list-records.favorites-views'),
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::RESOURCE_PAGES_MANAGE_RELATED_RECORDS_TABLE_BEFORE,
            fn () => view('filament.resources.pages.list-records.favorites-views'),
        );
    }
}
