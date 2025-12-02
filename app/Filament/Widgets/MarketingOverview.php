<?php

namespace App\Filament\Widgets;

use App\Models\Cliente;
use App\Models\IngredientBatch;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Artisan;
use Filament\Notifications\Notification;

class MarketingOverview extends BaseWidget
{
    /**
     * Orden del widget en el dashboard
     */
    protected static ?int $sort = 2;

    /**
     * Polling cada 5 minutos para actualizar datos
     */
    protected static ?string $pollingInterval = '300s';

    /**
     * Obtener las estadísticas de las 2 automatizaciones + botón de acción
     */
    protected function getStats(): array
    {
        return [
            $this->getActionButton(),
            $this->getWasteStat(),
            $this->getLoyaltyStat(),
        ];
    }

    /**
     * Tarjeta de Acción: Ejecutar Análisis Manual
     */
    protected function getActionButton(): Stat
    {
        return Stat::make('Análisis Manual', '🚀 Ejecutar Ahora')
            ->description('Click aquí para ejecutar los 3 análisis de mercado')
            ->descriptionIcon('heroicon-o-cpu-chip')
            ->color('primary')
            ->icon('heroicon-o-bolt')
            ->extraAttributes([
                'wire:click' => 'runMarketAnalysis',
                'class' => 'cursor-pointer hover:shadow-lg transition-shadow duration-200',
                'style' => 'cursor: pointer;',
            ]);
    }

    /**
     * Tarjeta 1: Anti-Desperdicio (Ingredientes en Riesgo)
     */
    protected function getWasteStat(): Stat
    {
        // Lista de ingredientes a ignorar (insumos base)
        $ignoredIngredients = [
            'Harina',
            'Levadura',
            'Sal',
            'Azúcar',
            'Agua',
            'Aceite',
            'Papas Congeladas',
            'Aceite de Oliva',
            'Vinagre',
            'Pimienta',
        ];

        // Contar lotes en riesgo (vencen en 3 días o menos)
        $expiringBatches = IngredientBatch::where('quantity', '>', 0)
            ->where('expiration_date', '<=', now()->addDays(3))
            ->where('expiration_date', '>=', now())
            ->whereHas('ingredient', fn($q) => $q->whereNotIn('name', $ignoredIngredients))
            ->count();

        // Agrupar por ingrediente único
        $uniqueIngredients = IngredientBatch::where('quantity', '>', 0)
            ->where('expiration_date', '<=', now()->addDays(3))
            ->where('expiration_date', '>=', now())
            ->whereHas('ingredient', fn($q) => $q->whereNotIn('name', $ignoredIngredients))
            ->with('ingredient')
            ->get()
            ->unique('ingredient_id')
            ->count();

        if ($uniqueIngredients === 0) {
            return Stat::make('Anti-Desperdicio', 'Sin Riesgos')
                ->description('✅ Todos los ingredientes bajo control')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success')
                ->icon('heroicon-o-shield-check');
        }

        return Stat::make('Anti-Desperdicio', "{$uniqueIngredients} Ingrediente" . ($uniqueIngredients > 1 ? 's' : ''))
            ->description("⚠️ {$expiringBatches} lote" . ($expiringBatches > 1 ? 's' : '') . " en riesgo de vencer")
            ->descriptionIcon('heroicon-o-exclamation-triangle')
            ->chart([$expiringBatches, $expiringBatches - 1, $expiringBatches + 2, $expiringBatches - 2, $expiringBatches + 1, $expiringBatches])
            ->color('danger')
            ->icon('heroicon-o-trash');
    }

    /**
     * Tarjeta 3: Fidelización (Cumpleaños + VIPs)
     */
    protected function getLoyaltyStat(): Stat
    {
        // Contar cumpleaños de hoy
        $birthdaysToday = Cliente::whereMonth('birthday', now()->month)
            ->whereDay('birthday', now()->day)
            ->count();

        // Contar clientes VIP (5+ pedidos en 30 días)
        $vipClients = Cliente::whereHas('orders', function ($query) {
            $query->where('created_at', '>=', now()->subDays(30));
        }, '>=', 5)->count();

        $total = $birthdaysToday + $vipClients;

        if ($total === 0) {
            return Stat::make('Fidelización', 'Sin Oportunidades')
                ->description('No hay cumpleaños ni nuevos VIPs hoy')
                ->descriptionIcon('heroicon-o-users')
                ->color('gray')
                ->icon('heroicon-o-user-group');
        }

        $description = [];
        if ($birthdaysToday > 0) {
            $description[] = "🎂 {$birthdaysToday} cumpleaño" . ($birthdaysToday > 1 ? 's' : '');
        }
        if ($vipClients > 0) {
            $description[] = "👑 {$vipClients} VIP" . ($vipClients > 1 ? 's' : '');
        }

        return Stat::make('Fidelización', "{$total} Oportunidad" . ($total > 1 ? 'es' : ''))
            ->description(implode(' • ', $description))
            ->descriptionIcon($birthdaysToday > 0 ? 'heroicon-o-cake' : 'heroicon-o-star')
            ->chart([$birthdaysToday, $vipClients, $birthdaysToday + $vipClients, 0, $birthdaysToday, $vipClients])
            ->color('success')
            ->icon('heroicon-o-heart');
    }

    /**
     * Ejecutar análisis de mercado manualmente
     */
    public function runMarketAnalysis(): void
    {
        try {
            // Ejecutar comandos de automatización
            Artisan::call('promo:check-weather');
            Artisan::call('stock:check-expiry');
            Artisan::call('loyalty:check-promo');

            // Notificación de éxito
            Notification::make()
                ->title('✅ Análisis completado exitosamente')
                ->body('Los tres análisis de mercado se ejecutaron correctamente. Revise sus notificaciones para ver las recomendaciones.')
                ->success()
                ->duration(8000)
                ->send();

            // Refrescar el widget para mostrar datos actualizados
            $this->dispatch('$refresh');
        } catch (\Exception $e) {
            Notification::make()
                ->title('❌ Error al ejecutar análisis')
                ->body("Ocurrió un error: {$e->getMessage()}")
                ->danger()
                ->persistent()
                ->send();
        }
    }
}
