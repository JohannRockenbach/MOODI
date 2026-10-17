<?php

use App\Mail\PromoEmail;
use App\Models\CampaignDraft;
use App\Models\Cliente;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Support\CampaignSegment;
use Illuminate\Support\Facades\Mail;

/*
|--------------------------------------------------------------------------
| Helpers (single-restaurant: restaurant_id 1)
|--------------------------------------------------------------------------
*/

function marketingRestaurant(): Restaurant
{
    return Restaurant::factory()->create(['id' => 1]);
}

function marketingUser(Restaurant $restaurant): User
{
    return User::factory()->create([
        'name' => 'Admin Marketing',
        'email' => 'marketing+'.bin2hex(random_bytes(4)).'@moodi.com',
        'restaurant_id' => $restaurant->id,
    ]);
}

function marketingCliente(Restaurant $restaurant, array $overrides = []): Cliente
{
    return Cliente::query()->create(array_merge([
        'name' => 'Cliente Test',
        'email' => 'cliente+'.bin2hex(random_bytes(4)).'@test.local',
        'phone' => '111111',
        'birthday' => now()->subYearsNoOverflow(25)->format('Y-m-d'),
        'restaurant_id' => $restaurant->id,
    ], $overrides));
}

function marketingDueCampaign(User $user, Restaurant $restaurant, array $overrides = []): CampaignDraft
{
    return CampaignDraft::query()->create(array_merge([
        'user_id' => $user->id,
        'restaurant_id' => $restaurant->id,
        'name' => 'Campaña Programada Test',
        'subject' => 'Promo especial de prueba',
        'body' => '**20% de descuento** en tu próxima visita.',
        'discount_type' => 'percentage',
        'discount_value' => 20,
        'coupon_code' => 'PROMO20',
        'segment' => CampaignSegment::TODOS,
        'scheduled_date' => now()->subMinute(),
        'valid_until' => now()->addDays(3),
        'status' => 'scheduled',
    ], $overrides));
}

/*
|--------------------------------------------------------------------------
| campaign:send-scheduled
|--------------------------------------------------------------------------
*/

describe('campaign:send-scheduled', function () {
    test('envía las campañas vencidas a todos los clientes con email y las marca como enviadas (happy path)', function () {
        $restaurant = marketingRestaurant();
        $user = marketingUser($restaurant);
        $clienteA = marketingCliente($restaurant);
        $clienteB = marketingCliente($restaurant);
        $campaign = marketingDueCampaign($user, $restaurant);

        Mail::fake();

        $this->artisan('campaign:send-scheduled')->assertSuccessful();

        expect($campaign->refresh()->status)->toBe('sent');

        Mail::assertSent(PromoEmail::class, $clienteA->email);
        Mail::assertSent(PromoEmail::class, $clienteB->email);
    });

    test('no envía borradores ni campañas con fecha futura (control/edge)', function () {
        $restaurant = marketingRestaurant();
        $user = marketingUser($restaurant);
        $cliente = marketingCliente($restaurant);

        $due = marketingDueCampaign($user, $restaurant);
        $future = marketingDueCampaign($user, $restaurant, [
            'name' => 'Campaña Futura',
            'scheduled_date' => now()->addDay(),
        ]);
        $draft = marketingDueCampaign($user, $restaurant, [
            'name' => 'Borrador',
            'scheduled_date' => null,
            'status' => 'draft',
        ]);

        Mail::fake();

        $this->artisan('campaign:send-scheduled')->assertSuccessful();

        expect($due->refresh()->status)->toBe('sent')
            ->and($future->refresh()->status)->toBe('scheduled')
            ->and($draft->refresh()->status)->toBe('draft');

        // Solo la campaña vencida se envía: exactamente 1 mail al cliente.
        Mail::assertSent(PromoEmail::class, 1);
        Mail::assertSent(PromoEmail::class, $cliente->email);
    });

    test('sin clientes en el segmento no envía y conserva la campaña como programada (edge)', function () {
        $restaurant = marketingRestaurant();
        $user = marketingUser($restaurant);

        // Cliente con cumpleaños en OTRO mes: no aplica al segmento 'cumpleanos'.
        marketingCliente($restaurant, [
            'birthday' => now()->subMonthsNoOverflow(3)->format('Y-m-d'),
        ]);

        $campaign = marketingDueCampaign($user, $restaurant, [
            'segment' => CampaignSegment::CUMPLEANOS,
        ]);

        Mail::fake();

        $this->artisan('campaign:send-scheduled')->assertSuccessful();

        expect($campaign->refresh()->status)->toBe('scheduled');
        Mail::assertNothingSent();
    });
});

/*
|--------------------------------------------------------------------------
| CampaignSegment (lógica compartida con la página SendCampaign)
|--------------------------------------------------------------------------
*/

describe('CampaignSegment', function () {
    test('segmento cumpleaños selecciona solo clientes con cumpleaños en el mes actual', function () {
        $restaurant = marketingRestaurant();
        $birthdayClient = marketingCliente($restaurant); // cumple este mes (helper usa subYears)
        $otherMonthClient = marketingCliente($restaurant, [
            'birthday' => now()->subMonthsNoOverflow(3)->format('Y-m-d'),
        ]);
        $noEmailClient = marketingCliente($restaurant, ['email' => '']);

        $ids = CampaignSegment::clientesForSegment(CampaignSegment::CUMPLEANOS, $restaurant->id)
            ->pluck('id');

        expect($ids)->toContain($birthdayClient->id)
            ->not->toContain($otherMonthClient->id)
            ->not->toContain($noEmailClient->id);
    });

    test('segmento vip selecciona clientes con 5+ pedidos en los últimos 30 días', function () {
        $restaurant = marketingRestaurant();
        $user = marketingUser($restaurant);

        $vip = marketingCliente($restaurant);
        $regular = marketingCliente($restaurant);

        foreach (range(1, 5) as $i) {
            Order::query()->create([
                'status' => 'completed',
                'waiter_id' => $user->id,
                'restaurant_id' => $restaurant->id,
                'customer_id' => $vip->id,
            ]);
        }

        $ids = CampaignSegment::clientesForSegment(CampaignSegment::VIP, $restaurant->id)
            ->pluck('id');

        expect($ids)->toContain($vip->id)
            ->not->toContain($regular->id);
    });
});
