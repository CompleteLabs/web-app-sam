<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use Apriansyahrs\CustomFields\CustomFieldsPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Rmsramos\Activitylog\ActivitylogPlugin;

class AdminPanelProvider extends PanelProvider
{
    /**
     * Configure the Filament admin panel.
     *
     * Features:
     * - Telescope integration with dynamic path from env
     * - SUPER ADMIN only access for system tools
     * - Custom fields plugin with role-based authorization
     */
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->colors([
                'primary' => Color::Amber,
            ])
            ->databaseNotifications()
            ->theme(asset('css/filament/admin/theme.css'))
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(MaxWidth::Full)
            ->sidebarWidth('16rem')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                // Dashboard akan menggunakan widgets melalui discoverWidgets
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugins([
                (new CustomFieldsPlugin)
                    ->authorize(fn(): bool => Auth::user()?->role?->name === 'SUPER ADMIN'),
                ActivitylogPlugin::make()
                    ->navigationGroup('System')
                    ->authorize(fn() => Auth::user()?->role?->name === 'SUPER ADMIN'),
            ])
            ->navigationItems([
                NavigationItem::make('Telescope')
                    ->url(fn() => url(env('TELESCOPE_PATH', 'telescope')))
                    ->icon('heroicon-o-magnifying-glass-circle')
                    ->group('System')
                    ->sort(999)
                    ->openUrlInNewTab()
                    ->visible(fn(): bool => Auth::check() && Auth::user()?->role?->name === 'SUPER ADMIN'),
                NavigationItem::make('Log Viewer')
                    ->url(fn() => url('log-viewer'))
                    ->icon('heroicon-o-document-text')
                    ->group('System')
                    ->sort(998)
                    ->openUrlInNewTab()
                    ->visible(fn(): bool => Auth::check() && Auth::user()?->role?->name === 'SUPER ADMIN'),
            ]);
    }
}
