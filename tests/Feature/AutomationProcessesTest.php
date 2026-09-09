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

        // The command groups by ingredient_id and uses that id as the variation index,
        // so the recipe style depends on the actual row id. Quantity (1000) stays under
        // 1500, so exactly one variation (v = 0) is generated: index = (id * 2) % 5.
        // Only 'Lovers Burger XL' (index 4) uses a x4 multiplier; the rest use x3.
        $variationIndex = ($cheddar->id * 2) % 5;
        $multiplier = $variationIndex === 4 ? 4 : 3;

        // baseCost = pan 100 + carne 250 + cheddar (300 * multiplier).
        // A regression to the old non-existent `unit_cost` fallback would yield
        // (50 + 200 + 0) * 1.30 = 325, so this proves purchase_price is used.
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
});
