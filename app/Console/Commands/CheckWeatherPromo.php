<?php

namespace App\Console\Commands;

use App\Filament\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\Restaurant;
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
        $this->info('🎯 Analizando escenario óptimo...');

        // Obtener configuración de umbrales desde base de datos
        $restaurant = Restaurant::find(1);
        $settings = $restaurant?->marketing_settings ?? [];
        $thresholds = config('marketing.weather.thresholds');
        
        // Usar umbrales personalizados si existen
        $heatThreshold = $settings['temp_heat_threshold'] ?? $thresholds['extreme_heat']['min_temp'];
        $coldThreshold = $settings['temp_cold_threshold'] ?? $thresholds['cold']['max_temp'];
        $rainProbability = $settings['rain_probability'] ?? 50;
        
        $scenario = null;
        $title = '';
        $body = '';
        $icon = 'heroicon-o-light-bulb';
        $iconColor = 'success';
        $products = [];
        $discountType = 'percentage';
        $discountValue = 20;
        $couponCode = 'CLIMA20';

        // ESCENARIO A: CALOR EXTREMO
        if ($temp > $heatThreshold) {
            $config = $thresholds['extreme_heat'];
            $this->info("   🔥 Escenario detectado: CALOR EXTREMO ({$temp}°C) → Estrategia \"Pack Refrescante\"");
            
            $product = $this->findProduct($config['products'], $config['min_stock']);

            if ($product) {
                $scenario = 'extreme_heat';
                $title = "☀️ ¡Combate el calor!";
                $body = "☀️ ¡Combate el calor! Tenemos las pintas más heladas de la ciudad esperándote. 2x1 en Cervezas Artesanales.\n\n🍺 Aprovecha esta oferta exclusiva y refresca tu día.";
                $icon = 'heroicon-o-fire';
                $iconColor = 'danger';
                $products = ['main' => $product];
                $discountValue = $config['discount'];
                $couponCode = $config['coupon_prefix'] . date('md');
                $this->line("   ✅ Producto seleccionado: {$product->name}");
            } else {
                $this->warn('   ⚠️  No hay productos disponibles para Pack Refrescante');
            }
        }
        // ESCENARIO B: DESCENSO BRUSCO (FRÍO)
        elseif ($temp < $coldThreshold) {
            $config = $thresholds['cold'];
            $this->info("   ❄️  Escenario detectado: DESCENSO BRUSCO ({$temp}°C) → Estrategia \"Combo Calentito\"");
            
            $product = $this->findProduct($config['products'], $config['min_stock']);

            if ($product) {
                $scenario = 'cold';
                $title = "❄️ ¡Combo Calentito Perfecto!";
                $body = "❄️ ¡Qué frío hace! Nada mejor que un {$product->name} para entrar en calor.\n\n☕ Pedilo ahora con descuento exclusivo. ¡Te va a encantar!";
                $icon = 'heroicon-o-fire';
                $iconColor = 'info';
                $products = ['main' => $product];
                $discountValue = $config['discount'];
                $couponCode = $config['coupon_prefix'] . date('md');
                $this->line("   ✅ Producto seleccionado: {$product->name}");
            } else {
                $this->warn('   ⚠️  No hay productos disponibles para Combo Calentito');
            }
        }
        // ESCENARIO C: LLUVIA
        elseif ($isRaining) {
            $config = $thresholds['rainy'];
            $this->info('   🌧️  Escenario detectado: LLUVIA → Estrategia "Combo Netflix"');
            
            $product = $this->findProduct($config['products'], $config['min_stock']);

            if ($product) {
                $scenario = 'rain';
                $title = "🌧️ ¡Planazo para hoy!";
                $body = "🌧️ ¡Planazo para hoy! Llueve en la ciudad y lo último que quieres es salir. Te llevamos el Combo Netflix a tu puerta.\n\n🏠 Pedí ahora y disfrutá sin moverte del sillón.";
                $icon = 'heroicon-o-cloud';
                $iconColor = 'primary';
                $products = ['main' => $product];
                $discountValue = $config['discount'];
                $couponCode = $config['coupon_prefix'] . date('md');
                $this->line("   ✅ Producto seleccionado: {$product->name}");
            } else {
                $this->warn('   ⚠️  No hay productos disponibles para Combo Netflix');
            }
        }
        // ESCENARIO D: CALOR MODERADO
        elseif ($temp > $thresholds['hot']['min_temp'] && $temp <= $thresholds['hot']['max_temp']) {
            $config = $thresholds['hot'];
            $this->info("   ☀️  Escenario detectado: CALOR MODERADO ({$temp}°C) → Estrategia \"After Office\"");
            
            $product = $this->findProduct($config['products'], $config['min_stock']);

            if ($product) {
                $scenario = 'heat';
                $title = "☀️ ¡Día perfecto para compartir!";
                $body = "☀️ ¡Qué lindo día! Aprovecha y disfrutá de nuestro {$product->name} con amigos.\n\n🎉 Oferta especial para que tu día sea inolvidable.";
                $icon = 'heroicon-o-sun';
                $iconColor = 'warning';
                $products = ['main' => $product];
                $discountValue = $config['discount'];
                $couponCode = $config['coupon_prefix'] . date('md');
                $this->line("   ✅ Producto seleccionado: {$product->name}");
            } else {
                $this->warn('   ⚠️  No hay productos disponibles para Combo After Office');
            }
        }
        // ESCENARIO E: CLIMA AGRADABLE
        else {
            $config = $thresholds['pleasant'];
            $this->info('   🍽️  Escenario detectado: CLIMA AGRADABLE → Estrategia "Menú del Día"');
            
            $product = $this->findProduct($config['products'], $config['min_stock']);

            if ($product) {
                $scenario = 'standard';
                $title = "🍽️ ¡Momento perfecto para disfrutar!";
                $body = "🍽️ ¡Día ideal! Aprovecha y probá nuestro delicioso {$product->name}.\n\n✨ Oferta especial disponible hoy. ¡No te lo pierdas!";
                $icon = 'heroicon-o-shopping-bag';
                $iconColor = 'success';
                $products = ['main' => $product];
                $discountValue = $config['discount'];
                $couponCode = $config['coupon_prefix'] . date('md');
                $this->line("   ✅ Producto seleccionado: {$product->name}");
            } else {
                $this->warn('   ⚠️  No hay productos disponibles para Menú del Día');
            }
        }

        // Si no hay escenario válido, terminar
        if (!$scenario) {
            $this->line('');
            $this->warn('❌ No se pudo generar ninguna estrategia de combo.');
            $this->comment('💡 Verifica que haya productos disponibles para activar el motor de marketing.');
            return Command::SUCCESS;
        }

        // ========================================
        // Paso 3: Notificar a Administradores
        // ========================================
        $this->line('');
        $this->info("✅ Oportunidad detectada: {$scenario}");

        $admins = User::whereHas('roles', function ($query) {
            $query->where('name', 'super_admin');
        })->get();

        if ($admins->isEmpty()) {
            $this->warn('⚠️  No se encontraron administradores para notificar.');
            return Command::SUCCESS;
        }

        $this->info('📧 Enviando notificación...');

        // Preparar URL para campaña
        $campaignUrl = \App\Filament\Pages\SendCampaign::getUrl([
            'subject' => $title,
            'body' => $body,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'coupon_code' => $couponCode,
        ]);

        Notification::make()
            ->title("🌤️ PROMOCIÓN POR CLIMA: " . $title)
            ->body($body . "\n\n⚡ Esta promoción fue generada automáticamente según el clima actual.")
            ->icon($icon)
            ->iconColor($iconColor)
            ->actions([
                Action::make('create_campaign')
                    ->label('🌤️ Crear Campaña de Clima')
                    ->icon('heroicon-o-megaphone')
                    ->color('success')
                    ->button()
                    ->url($campaignUrl),
            ])
            ->sendToDatabase($admins);

        $this->info('✅ Notificación enviada exitosamente.');

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
            ->filter(fn($product) => $product->stock > 20);

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
            // Primero intentar buscar por categoría
            $category = Category::where('name', 'like', "%{$categoryName}%")->first();

            if ($category) {
                $product = Product::where('category_id', $category->id)
                    ->get()
                    ->filter(fn($p) => $p->stock >= $minStock)
                    ->sortByDesc('stock')
                    ->first();

                if ($product) {
                    return $product;
                }
            }
            
            // Si no se encontró por categoría, buscar por nombre de producto
            $product = Product::where('name', 'like', "%{$categoryName}%")
                ->get()
                ->filter(fn($p) => $p->stock >= $minStock)
                ->sortByDesc('stock')
                ->first();
                
            if ($product) {
                return $product;
            }
        }

        return null;
    }
}
