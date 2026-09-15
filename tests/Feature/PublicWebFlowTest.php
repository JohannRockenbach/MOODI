<?php

namespace Tests\Feature;

use App\Livewire\AuthModal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Reproduce los bugs reportados del flujo web público:
 * 1. Registro nuevo → quedaba autenticado otro usuario ("Gonzalo").
 * 2. Carrito (cart-add) no agrega productos.
 */
class PublicWebFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Single-restaurant: restaurante 1 + roles como en producción.
        \App\Models\Restaurant::factory()->create(['id' => 1]);
        foreach (['super_admin', 'Mozo', 'Cajero', 'cliente'] as $role) {
            \Spatie\Permission\Models\Role::findOrCreate($role, 'web');
        }
    }

    public function test_registro_web_autentica_al_usuario_recien_creado(): void
    {
        $email = 'nuevo+'.bin2hex(random_bytes(4)).'@test.com';

        Livewire::test(AuthModal::class)
            ->set('name', 'Cliente Nuevo')
            ->set('email', $email)
            ->set('birthday', '1990-01-01')
            ->set('password', 'secret123')
            ->set('password_confirmation', 'secret123')
            ->call('register');

        $this->assertAuthenticated();
        $this->assertSame('Cliente Nuevo', auth()->user()->name);
        $this->assertSame($email, auth()->user()->email);
    }

    public function test_registro_web_no_reutiliza_un_usuario_staff_existente_por_email(): void
    {
        // "Gonzalo" (super_admin) existe con este email en producción.
        $gonzalo = User::factory()->create([
            'name' => 'Gonzalo',
            'email' => 'gonzalo@moodi.com',
        ]);
        $gonzalo->assignRole('super_admin');

        // Registrar un usuario NUEVO con distinto email.
        Livewire::test(AuthModal::class)
            ->set('name', 'Otro Cliente')
            ->set('email', 'otro+'.bin2hex(random_bytes(4)).'@test.com')
            ->set('birthday', '1992-02-02')
            ->set('password', 'secret123')
            ->set('password_confirmation', 'secret123')
            ->call('register');

        $this->assertAuthenticated();
        $this->assertNotSame('Gonzalo', auth()->user()->name);
        $this->assertSame('Otro Cliente', auth()->user()->name);
    }

    public function test_cart_add_agrega_producto_al_carrito(): void
    {
        $restaurant = \App\Models\Restaurant::query()->firstOrFail();

        $product = \App\Models\Product::factory()->create([
            'name' => 'Pizza Test',
            'restaurant_id' => $restaurant->id,
            'price' => 1500,
            'stock' => 10,
        ]);

        // El componente Volt cart-panel se testea por nombre de vista.
        Livewire::test('cart-panel')
            ->dispatch('cart-add', productId: $product->id)
            ->assertSet('cartItems.'.$product->id.'.quantity', 1);
    }

    public function test_cart_add_acepta_payload_con_clave_productId(): void
    {
        $restaurant = \App\Models\Restaurant::query()->firstOrFail();

        $product = \App\Models\Product::factory()->create([
            'name' => 'Hamburguesa Test',
            'restaurant_id' => $restaurant->id,
            'price' => 2000,
            'stock' => 5,
        ]);

        // El evento desde el botón: Livewire.dispatch('cart-add', { productId: X })
        Livewire::test('cart-panel')
            ->dispatch('cart-add', ['productId' => $product->id])
            ->assertSet('cartItems.'.$product->id.'.quantity', 1);
    }
}