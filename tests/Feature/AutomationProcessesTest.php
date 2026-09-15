<?php

use App\Models\Category;
use App\Models\Cliente;
use App\Models\Ingredient;
use App\Models\IngredientBatch;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\WeatherService;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/*
|--------------------------------------------------------------------------
| Helpers (single-restaurant: restaurant_id 1, roles: super_admin / Mozo)
|--------------------------------------------------------------------------
*/

function automationRestaurant(): Restaurant
{
    return Restaurant::factory()->create(['id' => 1]);
}

function automationUser(Restaurant $restaurant, string $role, string $emailPrefix): User
{
    Role::findOrCreate($role, 'web');

    $user = User::factory()->create([
        'name' => $role,
        'email' => $emailPrefix.'+'.bin2hex(random_bytes(4)).'@moodi.com',
        'restaurant_id' => $restaurant->id,
    ]);

    $user->assignRole($role);

    return $user;
}

function automationAdmin(Restaurant $restaurant): User
{
    return automationUser($restaurant, 'super_admin', 'admin');
}

function automationStaff(Restaurant $restaurant): User
{
    return automationUser($restaurant, 'Mozo', 'staff');
}

function automationNotifications(): Illuminate\Support\Collection
{
    return DB::table('notifications')->get();
}

function automationNotificationBodyFor(User $user): ?string
{
    $row = DB::table('notifications')->where('notifiable_id', $user->id)->first();

    if (! $row) {
        return null;
    }

    return json_decode($row->data, true)['body'] ?? null;
}

function automationWeatherWithRain(): void
{
    test()->mock(WeatherService::class, function ($mock) {
        $mock->shouldReceive('getCurrentWeather')->andReturn([
            'current' => [
                'temperature_2m' => 20.0,
                'is_day' => 1,
                'precipitation' => 2.5,
                'rain' => 2.5,
                'weather_code' => 63,
            ],
        ]);
        $mock->shouldReceive('isRaining')->andReturn(true);
    });
}

function automationWeatherUnavailable(): void
{
    test()->mock(WeatherService::class, function ($mock) {
        $mock->shouldReceive('getCurrentWeather')->andReturn(null);
        $mock->shouldReceive('isRaining')->andReturn(false);
    });
}

/*
|--------------------------------------------------------------------------
| promo:check-weather
|--------------------------------------------------------------------------
*/

describe('promo:check-weather', function () {
    function automationBurgerMenu(): array
    {
        $category = Category::query()->create([
            'name' => 'Hamburguesas Test',
            'description' => 'Hamburguesas del menú',
            'display_order' => 1,
        ]);

        $product = Product::factory()->create([
            'name' => 'Hamburguesa Clásica Test',
            'category_id' => $category->id,
            'restaurant_id' => 1,
            'price' => 500,
            'stock' => 50,
        ]);

        return [$category, $product];
    }

    test('notifies super_admin when a rain scenario is detected (happy path)', function () {
        $restaurant = automationRestaurant();
        $admin = automationAdmin($restaurant);
        automationBurgerMenu();
        automationWeatherWithRain();

        $this->artisan('promo:check-weather')->assertSuccessful();

        $notifications = automationNotifications();

        expect($notifications)->toHaveCount(1)
            ->and($notifications->first()->notifiable_id)->toBe($admin->id);

        $data = json_decode($notifications->first()->data, true);

        expect($data['title'])->toContain('PROMOCIÓN POR CLIMA')
            ->and($data['body'])->toContain('Planazo para hoy');
    });

    test('only notifies users with the super_admin role (authorization)', function () {
        $restaurant = automationRestaurant();
        $admin = automationAdmin($restaurant);
        $staff = automationStaff($restaurant);
        automationBurgerMenu();
        automationWeatherWithRain();

        $this->artisan('promo:check-weather')->assertSuccessful();

        $notifications = automationNotifications();

        expect($notifications)->toHaveCount(1)
            ->and($notifications->first()->notifiable_id)->toBe($admin->id)
            ->and(automationNotifications()->where('notifiable_id', $staff->id))->toBeEmpty();
    });

    test('fails gracefully and sends nothing when weather API returns no data (edge)', function () {
        $restaurant = automationRestaurant();
        automationAdmin($restaurant);
        automationBurgerMenu();
        automationWeatherUnavailable();

        $this->artisan('promo:check-weather')->assertExitCode(1);

        expect(automationNotifications())->toBeEmpty();
    });

    test('exits successfully without notifying when no eligible product exists (edge)', function () {
        $restaurant = automationRestaurant();
        automationAdmin($restaurant);
        automationWeatherWithRain();

        $this->artisan('promo:check-weather')->assertSuccessful();

        expect(automationNotifications())->toBeEmpty();
    });
});

/*
|--------------------------------------------------------------------------
| loyalty:check-promo
|--------------------------------------------------------------------------
*/

describe('loyalty:check-promo', function () {
    test('notifies super_admin when a client has a birthday today (happy path)', function () {
        $restaurant = automationRestaurant();
        $admin = automationAdmin($restaurant);

        $client = Cliente::query()->create([
            'name' => 'Cliente Cumpleañero',
            'email' => 'cumple+'.bin2hex(random_bytes(4)).'@test.local',
            'phone' => '111111111',
            'birthday' => now()->subYearsNoOverflow(30)->format('Y-m-d'),
            'restaurant_id' => $restaurant->id,
        ]);

        // El observer de Cliente ya disparó la notificación al crear (birthday = hoy).
        // Limpiamos para aislar el chequeo diario del comando.
        DB::table('notifications')->delete();

        $this->artisan('loyalty:check-promo')->assertSuccessful();

        $notifications = automationNotifications();

        expect($notifications)->toHaveCount(1)
            ->and($notifications->first()->notifiable_id)->toBe($admin->id);

        $data = json_decode($notifications->first()->data, true);

        expect($data['title'])->toContain('Feliz Cumpleaños, '.$client->name);
    });

    test('only notifies users with the super_admin role (authorization)', function () {
        $restaurant = automationRestaurant();
        $admin = automationAdmin($restaurant);
        $staff = automationStaff($restaurant);

        Cliente::query()->create([
            'name' => 'Cliente Cumpleaños Dos',
            'email' => 'cumple+'.bin2hex(random_bytes(4)).'@test.local',
            'phone' => '111111111',
            'birthday' => now()->subYearsNoOverflow(30)->format('Y-m-d'),
            'restaurant_id' => $restaurant->id,
        ]);

        // Aislar el comando: descartar la notificación del observer (mismo cliente).
        DB::table('notifications')->delete();

        $this->artisan('loyalty:check-promo')->assertSuccessful();

        $notifications = automationNotifications();

        expect($notifications)->toHaveCount(1)
            ->and($notifications->first()->notifiable_id)->toBe($admin->id)
            ->and($notifications->where('notifiable_id', $staff->id))->toBeEmpty();
    });

    test('sends nothing when no client has a birthday today (edge)', function () {
        $restaurant = automationRestaurant();
        automationAdmin($restaurant);

        Cliente::query()->create([
            'name' => 'Cliente Sin Cumpleaños',
            'email' => 'nobday+'.bin2hex(random_bytes(4)).'@test.local',
            'phone' => '111111111',
            // Same day of the month, but three months apart: never matches today.
            'birthday' => now()->subMonthsNoOverflow(3)->format('Y-m-d'),
            'restaurant_id' => $restaurant->id,
        ]);

        $this->artisan('loyalty:check-promo')->assertSuccessful();

        expect(automationNotifications())->toBeEmpty();
    });
});

/*
|--------------------------------------------------------------------------
| stock:check-expiry
|--------------------------------------------------------------------------
*/

describe('stock:check-expiry', function () {
    function automationBaseIngredients(Restaurant $restaurant): array
    {
        $pan = Ingredient::query()->create([
            'name' => 'Pan de Papa',
            'measurement_unit' => 'unidades',
            'reorder_point' => 0,
            'min_stock' => 10,
            'restaurant_id' => $restaurant->id,
        ]);
        $pan->forceFill(['purchase_price' => 100])->save();

        $carne = Ingredient::query()->create([
            'name' => 'Medallón Carne 120g',
            'measurement_unit' => 'unidades',
            'reorder_point' => 0,
            'min_stock' => 10,
            'restaurant_id' => $restaurant->id,
        ]);
        $carne->forceFill(['purchase_price' => 250])->save();

        return [$pan, $carne];
    }

    test('detects an expiring batch and prices the suggestion from purchase_price (happy path)', function () {
        $restaurant = automationRestaurant();
        $admin = automationAdmin($restaurant);
        automationBaseIngredients($restaurant);

        $cheddar = Ingredient::query()->create([
            'name' => 'Queso Cheddar',
            'measurement_unit' => 'gramos',
            'reorder_point' => 0,
            'min_stock' => 5,
            'restaurant_id' => $restaurant->id,
        ]);
        $cheddar->forceFill(['purchase_price' => 300])->save();

        IngredientBatch::query()->create([
            'ingredient_id' => $cheddar->id,
            'quantity' => 1000,
            'expiration_date' => now()->addDays(2),
        ]);

        // Fixture sanity check: purchase_price was persisted on the ingredients table.
        expect((float) DB::table('ingredients')->where('id', $cheddar->id)->value('purchase_price'))->toBe(300.0);

        $this->artisan('stock:check-expiry')->assertSuccessful();

        $notifications = automationNotifications();

        expect($notifications)->toHaveCount(1)
            ->and($notifications->first()->notifiable_id)->toBe($admin->id);

        $data = json_decode($notifications->first()->data, true);

        // El comando reindexa los riesgos 0..n por cantidad DESCENDENTE (fix del
        // hallazgo de variación nondeterminística). Con un único ingrediente en
        // riesgo el ranking es 0: variation[0] = 'Special Burger' (multiplicador 3).
        $variationIndex = 0;
        $multiplier = $variationIndex === 4 ? 4 : 3;

        // baseCost = pan 100 + carne 250 + cheddar (300 * multiplier).
        // Una regresión al viejo fallback `unit_cost` inexistente daría
        // (50 + 200 + 0) * 1.30 = 325, así que esto prueba que se usa purchase_price.
        $expectedPrice = (string) round((100 + 250 + (300 * $multiplier)) * 1.30, 2);

        expect($data['title'])->toContain('Idea de Nuevo Plato')
            ->and($data['body'])->toContain('Queso Cheddar')
            ->and($data['body'])->toContain('Precio: $'.$expectedPrice)
            ->and($data['body'])->not->toContain('Precio: $0');
    });

    test('only notifies users with the super_admin role (authorization)', function () {
        $restaurant = automationRestaurant();
        $admin = automationAdmin($restaurant);
        $staff = automationStaff($restaurant);
        automationBaseIngredients($restaurant);

        $cheddar = Ingredient::query()->create([
            'name' => 'Queso Cheddar',
            'measurement_unit' => 'gramos',
            'reorder_point' => 0,
            'min_stock' => 5,
            'restaurant_id' => $restaurant->id,
        ]);

        IngredientBatch::query()->create([
            'ingredient_id' => $cheddar->id,
            'quantity' => 1000,
            'expiration_date' => now()->addDays(2),
        ]);

        $this->artisan('stock:check-expiry')->assertSuccessful();

        $notifications = automationNotifications();

        expect($notifications)->toHaveCount(1)
            ->and($notifications->first()->notifiable_id)->toBe($admin->id)
            ->and($notifications->where('notifiable_id', $staff->id))->toBeEmpty();
    });

    test('sends nothing when there are no expiring batches (edge)', function () {
        $restaurant = automationRestaurant();
        automationAdmin($restaurant);
        automationBaseIngredients($restaurant);

        $this->artisan('stock:check-expiry')->assertSuccessful();

        expect(automationNotifications())->toBeEmpty();
    });

    test('fails when base ingredients (Pan / Carne) are missing (edge)', function () {
        $restaurant = automationRestaurant();
        automationAdmin($restaurant);

        $cheddar = Ingredient::query()->create([
            'name' => 'Queso Cheddar',
            'measurement_unit' => 'gramos',
            'reorder_point' => 0,
            'min_stock' => 5,
            'restaurant_id' => $restaurant->id,
        ]);

        IngredientBatch::query()->create([
            'ingredient_id' => $cheddar->id,
            'quantity' => 1000,
            'expiration_date' => now()->addDays(2),
        ]);

        $this->artisan('stock:check-expiry')->assertExitCode(1);

        expect(automationNotifications())->toBeEmpty();
    });

    test('variation index is deterministic by quantity ranking, not row id', function () {
        $restaurant = automationRestaurant();
        automationAdmin($restaurant);
        [$pan, $carne] = automationBaseIngredients($restaurant);

        // El ingrediente con MENOS cantidad se crea PRIMERO (id menor).
        $poco = Ingredient::query()->create([
            'name' => 'Tomate',
            'measurement_unit' => 'unidades',
            'reorder_point' => 0,
            'min_stock' => 5,
            'restaurant_id' => $restaurant->id,
        ]);
        $poco->forceFill(['purchase_price' => 50])->save();
        IngredientBatch::query()->create([
            'ingredient_id' => $poco->id,
            'quantity' => 100,
            'expiration_date' => now()->addDays(2),
        ]);

        // El ingrediente con MÁS cantidad se crea después (id mayor).
        $mucho = Ingredient::query()->create([
            'name' => 'Bacon',
            'measurement_unit' => 'gramos',
            'reorder_point' => 0,
            'min_stock' => 5,
            'restaurant_id' => $restaurant->id,
        ]);
        $mucho->forceFill(['purchase_price' => 400])->save();
        IngredientBatch::query()->create([
            'ingredient_id' => $mucho->id,
            'quantity' => 2000,
            'expiration_date' => now()->addDays(2),
        ]);

        $this->artisan('stock:check-expiry')->assertSuccessful();

        $notifications = automationNotifications();
        expect($notifications)->toHaveCount(3); // Bacon (qty 2000 => 2 variaciones) + Tomate (qty 100 => 1)

        $bodies = collect($notifications)
            ->map(fn ($n) => json_decode($n->data, true)['body'])
            ->join(' | ');

        // El ranking 0 (mayor cantidad = Bacon) debe caer en la variación 'Special'
        // (índice 0), NO depender del id de fila de Bacon.
        expect($bodies)->toContain('Bacon')
            ->and($bodies)->toContain('Special Bacon Burger');
    });

    test('detects excess stock when quantity exceeds monthly consumption (edge)', function () {
        $restaurant = automationRestaurant();
        $admin = automationAdmin($restaurant);
        [$pan, $carne] = automationBaseIngredients($restaurant);

        $cheddar = Ingredient::query()->create([
            'name' => 'Queso Cheddar',
            'measurement_unit' => 'gramos',
            'reorder_point' => 0,
            'min_stock' => 5,
            'restaurant_id' => $restaurant->id,
        ]);
        $cheddar->forceFill(['purchase_price' => 300])->save();

        IngredientBatch::query()->create([
            'ingredient_id' => $cheddar->id,
            'quantity' => 10000, // Muy por encima de cualquier consumo mensual
            'expiration_date' => now()->addDays(2),
        ]);

        // Consumo mensual: 1 producto vendido con receta que usa 200g de cheddar.
        $recipe = \App\Models\Recipe::query()->create([
            'name' => 'Burger Cheddar',
            'instructions' => 'Burger con cheddar, pan y carne',
        ]);
        $recipe->ingredients()->attach([
            $pan->id => ['required_amount' => 1],
            $carne->id => ['required_amount' => 1],
            $cheddar->id => ['required_amount' => 200],
        ]);

        $product = Product::factory()->create([
            'name' => 'Burger Cheddar Test',
            'restaurant_id' => $restaurant->id,
            'recipe_id' => $recipe->id,
            'price' => 1500,
            'stock' => 10,
        ]);

        $order = \App\Models\Order::factory()->create([
            'restaurant_id' => $restaurant->id,
            'status' => 'completed',
            'type' => 'salon',
            'table_id' => \App\Models\Table::factory()->create(['restaurant_id' => $restaurant->id])->id,
        ]);
        \App\Models\OrderProduct::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->artisan('stock:check-expiry')->assertSuccessful();

        $bodies = collect(automationNotifications())
            ->map(fn ($n) => json_decode($n->data, true)['body'])
            ->join(' | ');

        expect($bodies)->toContain('Queso Cheddar')
            ->and($bodies)->toContain('Exceso de stock');
    });

    test('does not flag excess when consumption equals or exceeds stock (edge)', function () {
        $restaurant = automationRestaurant();
        automationAdmin($restaurant);
        [$pan, $carne] = automationBaseIngredients($restaurant);

        $cheddar = Ingredient::query()->create([
            'name' => 'Queso Cheddar',
            'measurement_unit' => 'gramos',
            'reorder_point' => 0,
            'min_stock' => 5,
            'restaurant_id' => $restaurant->id,
        ]);
        $cheddar->forceFill(['purchase_price' => 300])->save();

        IngredientBatch::query()->create([
            'ingredient_id' => $cheddar->id,
            'quantity' => 100,
            'expiration_date' => now()->addDays(2),
        ]);

        // Consumo mensual alto: 500g (más que el stock de 100g => NO exceso).
        $recipe = \App\Models\Recipe::query()->create([
            'name' => 'Burger Cheddar 2',
            'instructions' => 'Burger con cheddar extra, pan y carne',
        ]);
        $recipe->ingredients()->attach([
            $pan->id => ['required_amount' => 1],
            $carne->id => ['required_amount' => 1],
            $cheddar->id => ['required_amount' => 500],
        ]);

        $product = Product::factory()->create([
            'name' => 'Burger Cheddar Test 2',
            'restaurant_id' => $restaurant->id,
            'recipe_id' => $recipe->id,
            'price' => 1500,
            'stock' => 10,
        ]);

        $order = \App\Models\Order::factory()->create([
            'restaurant_id' => $restaurant->id,
            'status' => 'completed',
            'type' => 'salon',
            'table_id' => \App\Models\Table::factory()->create(['restaurant_id' => $restaurant->id])->id,
        ]);
        \App\Models\OrderProduct::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->artisan('stock:check-expiry')->assertSuccessful();

        $bodies = collect(automationNotifications())
            ->map(fn ($n) => json_decode($n->data, true)['body'])
            ->join(' | ');

        expect($bodies)->toContain('Queso Cheddar')
            ->and($bodies)->not->toContain('Exceso de stock');
    });
});

/*
|--------------------------------------------------------------------------
| Disparo inmediato por cumpleaños al crear Cliente (ClienteObserver)
|--------------------------------------------------------------------------
*/

describe('loyalty promo on cliente creation', function () {
    test('crear un cliente con cumpleaños hoy notifica al instante a super_admin (happy path)', function () {
        $restaurant = automationRestaurant();
        $admin = automationAdmin($restaurant);

        $client = Cliente::query()->create([
            'name' => 'Cumpleaños Hoy',
            'email' => 'hoy+'.bin2hex(random_bytes(4)).'@test.local',
            'phone' => '111111111',
            'birthday' => now()->format('Y-m-d'),
            'restaurant_id' => $restaurant->id,
        ]);

        $notifications = automationNotifications();

        expect($notifications)->toHaveCount(1)
            ->and($notifications->first()->notifiable_id)->toBe($admin->id);

        $data = json_decode($notifications->first()->data, true);

        expect($data['title'])->toContain('Feliz Cumpleaños, '.$client->name);
    });

    test('solo notifica a usuarios con rol super_admin (autorización)', function () {
        $restaurant = automationRestaurant();
        $admin = automationAdmin($restaurant);
        $staff = automationStaff($restaurant);

        Cliente::query()->create([
            'name' => 'Cumpleaños Autorización',
            'email' => 'auth+'.bin2hex(random_bytes(4)).'@test.local',
            'phone' => '111111111',
            'birthday' => now()->format('Y-m-d'),
            'restaurant_id' => $restaurant->id,
        ]);

        $notifications = automationNotifications();

        expect($notifications)->toHaveCount(1)
            ->and($notifications->first()->notifiable_id)->toBe($admin->id)
            ->and($notifications->where('notifiable_id', $staff->id))->toBeEmpty();
    });

    test('cliente con cumpleaños distinto a hoy no notifica (edge)', function () {
        $restaurant = automationRestaurant();
        automationAdmin($restaurant);

        Cliente::query()->create([
            'name' => 'Cumpleaños Otro Día',
            'email' => 'otro+'.bin2hex(random_bytes(4)).'@test.local',
            'phone' => '111111111',
            // Mismo día del mes pero tres meses atrás: nunca coincide con hoy.
            'birthday' => now()->subMonthsNoOverflow(3)->format('Y-m-d'),
            'restaurant_id' => $restaurant->id,
        ]);

        expect(automationNotifications())->toBeEmpty();
    });

    test('cliente sin birthday no rompe ni notifica (edge)', function () {
        $restaurant = automationRestaurant();
        automationAdmin($restaurant);

        Cliente::query()->create([
            'name' => 'Sin Cumpleaños',
            'email' => 'sinbday+'.bin2hex(random_bytes(4)).'@test.local',
            'phone' => '111111111',
            'restaurant_id' => $restaurant->id,
        ]);

        expect(automationNotifications())->toBeEmpty();
    });

    test('sin admins super_admin no rompe (edge)', function () {
        $restaurant = automationRestaurant();

        Cliente::query()->create([
            'name' => 'Sin Admin',
            'email' => 'sinadmin+'.bin2hex(random_bytes(4)).'@test.local',
            'phone' => '111111111',
            'birthday' => now()->format('Y-m-d'),
            'restaurant_id' => $restaurant->id,
        ]);

        expect(automationNotifications())->toBeEmpty();
    });
});

/*
|--------------------------------------------------------------------------
| Scheduler: registración única (fix de registros duplicados)
|--------------------------------------------------------------------------
*/

describe('scheduler registration', function () {
    test('loyalty:check-promo está registrado UNA sola vez y a las 08:30', function () {
        // Inspección directa del contenedor: más estable que schedule:list (que
        // depende del formateo de salida y puede variar entre versiones de Laravel).
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events());

        $loyaltyEvents = $events->filter(fn ($event) => str_contains($event->command, 'loyalty:check-promo'));

        expect($loyaltyEvents)->toHaveCount(1)
            ->and($loyaltyEvents->first()->expression)->toBe('30 8 * * *');
    });
});
