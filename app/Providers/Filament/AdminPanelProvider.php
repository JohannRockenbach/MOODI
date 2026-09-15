<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\MarketingSettings;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->topNavigation()
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->defaultAvatarProvider(\Filament\AvatarProviders\UiAvatarsProvider::class)
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                \App\Filament\Pages\Dashboard::class,
                \App\Filament\Pages\MarketingSettings::class,
            ])
            ->navigationGroups([
                NavigationGroup::make('Operaciones')
                    ->label('Operaciones'),
                NavigationGroup::make('Inventario')
                    ->label('Inventario'),
                NavigationGroup::make('Clientes')
                    ->label('Clientes'),
                NavigationGroup::make('Reportes')
                    ->label('Reportes'),
                NavigationGroup::make('Configuración')
                    ->label('Configuración'),
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                // Widgets del sistema
                // Widgets\AccountWidget::class,
                // Widgets\FilamentInfoWidget::class,

                // Widgets personalizados del Dashboard
                \App\Filament\Widgets\WeatherOverview::class, // Estado del Clima (2 tarjetas)
                \App\Filament\Widgets\StockNotificationsWidget::class, // Notificaciones de stock
                \App\Filament\Widgets\MarketingOverview::class, // Anti-Desperdicio + Fidelización
                \App\Filament\Widgets\StatsOverview::class,
                \App\Filament\Widgets\SalesChart::class,
                \App\Filament\Widgets\LatestOrders::class,
                \App\Filament\Widgets\LowStockWidget::class,
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
                \App\Http\Middleware\RedirectMozoToTableMap::class,
                \App\Http\Middleware\RedirectCocineroToKitchen::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);

    }
}
