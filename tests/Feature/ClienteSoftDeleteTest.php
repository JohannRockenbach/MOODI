<?php

use App\Filament\Resources\ClienteResource;
use App\Models\Cliente;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Support\Str;

it('soft deletes a cliente (happy path)', function () {
    $restaurant = Restaurant::factory()->create();

    $cliente = Cliente::create([
        'restaurant_id' => $restaurant->id,
        'name' => 'Cliente Happy Path',
        'email' => 'cliente-happy-'.Str::uuid().'@example.com',
        'phone' => '+5491111111111',
    ]);

    $cliente->delete();

    $this->assertSoftDeleted('clientes', ['id' => $cliente->id]);
});

it('restores a soft deleted cliente', function () {
    $restaurant = Restaurant::factory()->create();

    $cliente = Cliente::create([
        'restaurant_id' => $restaurant->id,
        'name' => 'Cliente Restore',
        'email' => 'cliente-restore-'.Str::uuid().'@example.com',
    ]);

    $cliente->delete();
    $this->assertSoftDeleted('clientes', ['id' => $cliente->id]);

    $cliente->restore();

    $this->assertNotSoftDeleted('clientes', ['id' => $cliente->id]);
});

it('limits cliente resource query to authenticated user restaurant (authorization/edge)', function () {
    $restaurantA = Restaurant::factory()->create();
    $restaurantB = Restaurant::factory()->create();

    $user = User::factory()->create([
        'restaurant_id' => $restaurantA->id,
    ]);

    Cliente::create([
        'restaurant_id' => $restaurantA->id,
        'name' => 'Cliente Visible',
        'email' => 'cliente-visible-'.Str::uuid().'@example.com',
    ]);

    $clienteEliminadoVisible = Cliente::create([
        'restaurant_id' => $restaurantA->id,
        'name' => 'Cliente Eliminado Visible',
        'email' => 'cliente-eliminado-visible-'.Str::uuid().'@example.com',
    ]);
    $clienteEliminadoVisible->delete();

    $clienteOculto = Cliente::create([
        'restaurant_id' => $restaurantB->id,
        'name' => 'Cliente Oculto',
        'email' => 'cliente-oculto-'.Str::uuid().'@example.com',
    ]);

    $clienteOculto->delete();

    $this->actingAs($user);

    $idsVisibles = ClienteResource::getEloquentQuery()->pluck('id')->all();

    expect($idsVisibles)->toContain($clienteEliminadoVisible->id);
    expect($idsVisibles)->not->toContain($clienteOculto->id);
});
