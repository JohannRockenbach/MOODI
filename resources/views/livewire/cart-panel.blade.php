<?php

use App\Models\Product;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component
{
    public array $cartItems = [];

    public function mount(): void
    {
        $this->cartItems = session('cart', []);
    }

    protected function syncCart(): void
    {
        session()->put('cart', $this->cartItems);
        $this->dispatch('cart-updated', count: $this->cartCount);
    }

    #[Computed]
    public function cartCount(): int
    {
        return (int) collect($this->cartItems)->sum('quantity');
    }

    #[Computed]
    public function total(): float
    {
        return (float) collect($this->cartItems)->sum(fn ($item) => $item['price'] * $item['quantity']);
    }

    #[On('cart-add')]
    public function addToCart($productId): void
    {
        $productId = (int) $productId;
        $product = Product::find($productId);

        if (! $product) {
            return;
        }

        $this->cartItems[$productId] = [
            'name' => $product->name,
            'price' => (float) $product->price,
            'quantity' => ($this->cartItems[$productId]['quantity'] ?? 0) + 1,
        ];

        $this->syncCart();
    }

    public function incrementQuantity(int $productId): void
    {
        if (isset($this->cartItems[$productId])) {
            $this->cartItems[$productId]['quantity']++;
            $this->syncCart();
        }
    }

    public function decrementQuantity(int $productId): void
    {
        if (! isset($this->cartItems[$productId])) {
            return;
        }

        if ($this->cartItems[$productId]['quantity'] <= 1) {
            unset($this->cartItems[$productId]);
        } else {
            $this->cartItems[$productId]['quantity']--;
        }

        $this->syncCart();
    }

    public function removeFromCart(int $productId): void
    {
        unset($this->cartItems[$productId]);
        $this->syncCart();
    }
};

?>

<div
    x-data="{ open: false }"
    @open-cart.window="open = true"
    @close-cart.window="open = false"
    @keydown.escape.window="open = false"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 flex justify-end"
>
    <div class="absolute inset-0 bg-black/40" @click="open = false" aria-hidden="true"></div>

    <aside class="relative h-full w-full max-w-md bg-white shadow-2xl flex flex-col" role="dialog" aria-modal="true" aria-label="Carrito de compras" @click.stop>
        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 sm:px-6 sm:py-4">
            <h2 class="text-xl font-black text-gray-900">Tu Pedido</h2>
            <button
                type="button"
                class="rounded-full p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500"
                @click="open = false"
                aria-label="Cerrar carrito"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6l-12 12" />
                </svg>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto px-4 py-4 sm:px-6 space-y-3 sm:space-y-4">
            @if(count($cartItems) === 0)
                <div class="h-full min-h-[240px] flex items-center justify-center text-center">
                    <div>
                        <p class="text-gray-700 font-semibold">Tu carrito está vacío</p>
                        <p class="text-sm text-gray-500 mt-1">Agrega productos para comenzar tu pedido.</p>
                    </div>
                </div>
            @endif

            @foreach($cartItems as $id => $item)
                <article class="rounded-2xl border border-gray-100 bg-white p-3 sm:p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="font-bold text-gray-900 break-words">{{ $item['name'] }}</h3>
                            <p class="text-sm text-gray-500 mt-1">${{ number_format((float) $item['price'], 0, ',', '.') }} c/u</p>
                        </div>

                        <button
                            type="button"
                            wire:click="removeFromCart({{ $id }})"
                            class="rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-500 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500"
                            aria-label="Eliminar producto"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 6V4h8v2" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 6l-1 14H6L5 6" />
                            </svg>
                        </button>
                    </div>

                    <div class="mt-4 flex items-center justify-between gap-3">
                        <div class="inline-flex items-center rounded-xl border border-gray-200 shrink-0">
                            <button
                                type="button"
                                wire:click="decrementQuantity({{ $id }})"
                                class="px-3 py-1.5 text-gray-700 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500"
                            >
                                -
                            </button>
                            <span class="px-3 py-1.5 text-sm font-bold text-gray-800">{{ $item['quantity'] }}</span>
                            <button
                                type="button"
                                wire:click="incrementQuantity({{ $id }})"
                                class="px-3 py-1.5 text-gray-700 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500"
                            >
                                +
                            </button>
                        </div>

                        <span class="font-black text-orange-600 text-sm sm:text-base text-right break-words">
                            ${{ number_format((float) $item['price'] * (int) $item['quantity'], 0, ',', '.') }}
                        </span>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="border-t border-gray-100 px-4 py-4 sm:px-6 space-y-4">
            <div class="flex items-center justify-between">
                <span class="text-sm font-semibold text-gray-600">Subtotal</span>
                <span class="text-xl sm:text-2xl font-black text-gray-900">${{ number_format($this->total, 0, ',', '.') }}</span>
            </div>

            <a
                href="{{ route('checkout') }}"
                class="block w-full rounded-xl bg-orange-500 py-3 text-center text-sm font-bold text-white hover:bg-orange-600 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500 focus-visible:ring-offset-2 {{ $this->cartCount === 0 ? 'pointer-events-none opacity-50' : '' }}"
            >
                Ir a Pagar
            </a>
        </div>
    </aside>
</div>
