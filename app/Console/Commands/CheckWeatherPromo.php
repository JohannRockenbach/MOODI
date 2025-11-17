<?php

namespace App\Console\Commands;

use App\Filament\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\WeatherService;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;

class CheckWeatherPromo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'promo:check-weather';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analiza el clima y selecciona la hamburguesa ideal para promocionar';

    /**
     * Execute the console command.
     */
    public function handle(WeatherService $weatherService): int
    {
        $this->info('� Motor de Marketing Inteligente - Iniciando análisis...');

        // ========================================
        // Paso 1: Obtener Datos del Clima
        // ========================================
        $this->line('');
        $this->info('🌍 Consultando clima de Apóstoles, Misiones...');
        
        $weather = $weatherService->getCurrentWeather();

        if (!$weather) {
            $this->error('❌ No se pudo obtener datos del clima. Intenta más tarde.');
            return Command::FAILURE;
        }

        $temp = $weather['current']['temperature_2m'] ?? 0;
        $isRaining = $weatherService->isRaining($weather);

        $this->line("   🌡️  Temperatura: {$temp}°C");
        $this->line("   " . ($isRaining ? "🌧️  Estado: Lloviendo" : "☀️  Estado: Sin lluvia"));

        // ========================================
        // Paso 2: Determinar Escenario y Estrategia
        // ========================================
        $this->line('');
        $this->info('� Analizando escenario óptimo...');

        $scenario = null;
        $title = '';
        $body = '';
        $icon = 'heroicon-o-light-bulb';
        $iconColor = 'success';
        $products = [];

        // ESCENARIO A: LLUVIA - Combo Netflix (2 Burgers + 1 Bebida)
        if ($isRaining) {
            $this->info('   📺 Escenario detectado: LLUVIA → Estrategia "Combo Netflix"');
            
            $burger = $this->findIntermediatePriceBurger();
            $drink = $this->findProduct(['Bebida', 'Bebidas'], 10);

            if ($burger && $drink && $burger->real_stock >= 20 && $drink->real_stock >= 10) {
                $scenario = 'rain';
                $title = "🌧️ Oportunidad Lluvia: Combo 'Pareja'";
                $body = "Llueve en Apóstoles. **Sugerencia:** Promo '{$burger->name} x2 + {$drink->name}'. "
                    . "Stock disponible: {$burger->real_stock} burgers y {$drink->real_stock} bebidas.";
                $icon = 'heroicon-o-cloud';
                $iconColor = 'primary';
                $products = ['burger' => $burger, 'drink' => $drink];

                $this->line("   ✅ Burger seleccionada: {$burger->name} (Stock: {$burger->real_stock})");
                $this->line("   ✅ Bebida seleccionada: {$drink->name} (Stock: {$drink->real_stock})");
            } else {
                $this->warn('   ⚠️  Stock insuficiente para Combo Netflix');
                $this->line('       Requerido: Burger (≥20) + Bebida (≥10)');
            }
        }
        // ESCENARIO B: CALOR - Combo After Office (Cerveza + Papas)
        elseif ($temp > 28) {
            $this->info("   🔥 Escenario detectado: CALOR ({$temp}°C) → Estrategia \"After Office\"");
            
            $beer = $this->findProduct(['Pinta', 'Cerveza', 'Chopp'], 20);
            $fries = $this->findProduct(['Papa', 'Papas'], 20);

            if ($beer && $fries && $beer->real_stock >= 20 && $fries->real_stock >= 20) {
                $scenario = 'heat';
                $title = "☀️ ¡Qué Calor! Combo 'After Office'";
                $body = "¡{$temp}°C en Apóstoles! **Sugerencia:** Promo '{$beer->name} + {$fries->name}'. "
                    . "Stock disponible: {$beer->real_stock} cervezas y {$fries->real_stock} papas.";
                $icon = 'heroicon-o-sun';
                $iconColor = 'warning';
                $products = ['beer' => $beer, 'fries' => $fries];

                $this->line("   ✅ Cerveza seleccionada: {$beer->name} (Stock: {$beer->real_stock})");
                $this->line("   ✅ Papas seleccionadas: {$fries->name} (Stock: {$fries->real_stock})");
            } else {
                $this->warn('   ⚠️  Stock insuficiente para Combo After Office');
                $this->line('       Requerido: Cerveza (≥20) + Papas (≥20)');
            }
        }
        // ESCENARIO C: ESTÁNDAR - Combo Simple (Burger + Papa)
        else {
            $this->info('   🍽️  Escenario detectado: ESTÁNDAR → Estrategia "Menú Ejecutivo"');
            
            $burger = $this->findIntermediatePriceBurger();
            $fries = $this->findProduct(['Papa', 'Papas'], 15);

            if ($burger && $fries && $burger->real_stock >= 15 && $fries->real_stock >= 15) {
                $scenario = 'standard';
                $title = "🍽️ Día Tranquilo: Menú Ejecutivo";
                $body = "Condiciones estándar. **Sugerencia:** Promo '{$burger->name} + {$fries->name}'. "
                    . "Stock disponible: {$burger->real_stock} burgers y {$fries->real_stock} papas.";
                $icon = 'heroicon-o-shopping-bag';
                $iconColor = 'success';
                $products = ['burger' => $burger, 'fries' => $fries];

                $this->line("   ✅ Burger seleccionada: {$burger->name} (Stock: {$burger->real_stock})");
                $this->line("   ✅ Papas seleccionadas: {$fries->name} (Stock: {$fries->real_stock})");
            } else {
                $this->warn('   ⚠️  Stock insuficiente para Menú Ejecutivo');
                $this->line('       Requerido: Burger (≥15) + Papas (≥15)');
            }
        }

        // Si no hay escenario válido, terminar
        if (!$scenario) {
            $this->line('');
            $this->warn('❌ No se pudo generar ninguna estrategia de combo con el stock disponible.');
            $this->comment('💡 Reponer stock de productos clave para activar el motor de marketing.');
            return Command::SUCCESS;
        }

        // ========================================
        // Paso 3: Notificar a Administradores
        // ========================================
        $this->line('');
        $this->info("✅ Estrategia generada: {$scenario}");

        $admins = User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['super_admin', 'administrador']);
        })->get();

        if ($admins->isEmpty()) {
            $this->warn('⚠️  No se encontraron administradores para notificar.');
            return Command::SUCCESS;
        }

        $this->info('📧 Enviando notificación a ' . $admins->count() . ' administrador(es)...');

        // Preparar URLs para los botones
        // URL de la página de campaña con parámetros pre-llenados
        $campaignUrl = \App\Filament\Pages\SendCampaign::getUrl([
            'subject' => $title,
            'body' => $body,
        ]);
        
        // URL de la lista de productos
        $viewProductsUrl = ProductResource::getUrl('index');

        Notification::make()
            ->title($title)
            ->body($body)
            ->icon($icon)
            ->iconColor($iconColor)
            ->actions([
                Action::make('create_campaign')
                    ->label('Crear Campaña')
                    ->icon('heroicon-o-megaphone')
                    ->color('success')
                    ->button()
                    ->url($campaignUrl),
                
                Action::make('view_products')
                    ->label('Ver Productos')
                    ->icon('heroicon-o-shopping-bag')
                    ->color('gray')
                    ->url($viewProductsUrl)
                    ->openUrlInNewTab(true),
            ])
            ->sendToDatabase($admins);

        $this->line('');
        $this->info('✉️  Notificación enviada exitosamente.');
        $this->line('');
        $this->comment('💡 Motor de Marketing: Estrategia generada y notificación enviada.');
        $this->comment("💡 Escenario: {$scenario} | Clima: {$temp}°C | Lluvia: " . ($isRaining ? 'Sí' : 'No'));
        
        return Command::SUCCESS;
    }

    /**
     * Busca la hamburguesa con precio intermedio y stock suficiente
     */
    private function findIntermediatePriceBurger(): ?Product
    {
        $hamburgerCategory = Category::where('name', 'like', '%Hamburguesa%')->first();

        if (!$hamburgerCategory) {
            return null;
        }

        $burgers = Product::where('category_id', $hamburgerCategory->id)
            ->get()
            ->filter(fn($product) => $product->real_stock > 20);

        if ($burgers->isEmpty()) {
            return null;
        }

        $averagePrice = $burgers->avg('price');

        return $burgers->sortBy(fn($product) => abs($product->price - $averagePrice))->first();
    }

    /**
     * Busca un producto por categoría con stock mínimo
     */
    private function findProduct(array $categoryNames, int $minStock): ?Product
    {
        foreach ($categoryNames as $categoryName) {
            $category = Category::where('name', 'like', "%{$categoryName}%")->first();

            if ($category) {
                $product = Product::where('category_id', $category->id)
                    ->get()
                    ->filter(fn($p) => $p->real_stock >= $minStock)
                    ->sortByDesc('real_stock')
                    ->first();

                if ($product) {
                    return $product;
                }
            }
        }

        return null;
    }
}
