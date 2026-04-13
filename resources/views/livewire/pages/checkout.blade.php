<?php

use App\Events\OrderProcessing;
use App\Models\Caja;
use App\Models\Discount;
use App\Models\Order;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.checkout')] class extends Component
{
    public array $cartItems = [];

    public string $type = 'delivery';

    public string $delivery_address = '';

    public string $payment_method = 'efectivo';

    public ?string $cash_amount = null;

    public string $coupon_code = '';

    public float $discount_amount = 0;

    public ?int $applied_discount_id = null;

    public function mount(): void
    {
        $this->cartItems = session('cart', []);

        if (empty($this->cartItems)) {
            $this->redirect('/', navigate: true);

            return;
        }

        $user = Auth::user();

        /** @var User|null $user */
        if ($user && filled($user->address)) {
            $this->delivery_address = (string) $user->address;
        }
    }

    #[Computed]
    public function subtotal(): float
    {
        return (float) collect($this->cartItems)->sum(
            fn ($item) => ((float) $item['price']) * ((int) $item['quantity'])
        );
    }

    #[Computed]
    public function total(): float
    {
        return max(0.0, $this->subtotal - $this->discount_amount);
    }

    #[Computed]
    public function change(): float
    {
        if ($this->payment_method !== 'efectivo' || blank($this->cash_amount)) {
            return 0.0;
        }

        return max(0.0, (float) $this->cash_amount - $this->total);
    }

    public function applyCoupon(): void
    {
        $this->resetErrorBag('coupon_code');
        $this->discount_amount = 0;
        $this->applied_discount_id = null;

        $normalizedCode = strtoupper(trim($this->coupon_code));

        if (blank($normalizedCode)) {
            $this->addError('coupon_code', 'Ingresa un código de cupón.');

            return;
        }

        $user = Auth::user();

        /** @var User|null $user */
        $restaurantId = $user?->restaurant_id ?: Restaurant::query()->value('id');

        $discount = Discount::query()
            ->whereRaw('UPPER(code) = ?', [$normalizedCode])
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->when($restaurantId, fn ($query) => $query->where('restaurant_id', $restaurantId))
            ->first();

        if (! $discount) {
            $this->addError('coupon_code', 'Cupón inválido');

            return;
        }

        $amount = $discount->type === 'percentage'
            ? ($this->subtotal * ((float) $discount->value / 100))
            : (float) $discount->value;

        $this->discount_amount = max(0.0, min($this->subtotal, $amount));
        $this->applied_discount_id = $discount->id;
        $this->coupon_code = $normalizedCode;
    }

    public function placeOrder(): void
    {
        if (empty($this->cartItems)) {
            $this->redirect('/', navigate: true);

            return;
        }

        $this->validate([
            'type' => ['required', 'in:delivery,para_llevar'],
            'delivery_address' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['required', 'in:efectivo,tarjeta,transferencia'],
            'cash_amount' => ['nullable', 'numeric', 'min:0'],
        ], [], [
            'type' => 'tipo de entrega',
            'delivery_address' => 'dirección de envío',
            'payment_method' => 'método de pago',
            'cash_amount' => 'monto en efectivo',
        ]);

        if ($this->type === 'delivery' && blank($this->delivery_address)) {
            $this->addError('delivery_address', 'La dirección de envío es obligatoria para delivery.');

            return;
        }

        if ($this->payment_method === 'efectivo' && blank($this->cash_amount)) {
            $this->addError('cash_amount', 'Debes indicar con cuánto vas a abonar en efectivo.');

            return;
        }

        if (filled(trim($this->coupon_code)) && ! $this->applied_discount_id) {
            $this->addError('coupon_code', 'Debes aplicar un cupón válido antes de continuar.');

            return;
        }

        $user = Auth::user();

        if (! $user) {
            $this->redirect(route('login'), navigate: true);

            return;
        }

        $restaurantId = Restaurant::query()->whereKey(1)->value('id')
            ?: ($user->restaurant_id ?: Restaurant::query()->value('id'));

        if (! $restaurantId) {
            $this->addError('type', 'No hay ningún restaurante configurado en el sistema.');

            return;
        }

        $cartSnapshot = $this->cartItems;
        $orderType = $this->type;
        $address = $this->delivery_address;
        $payMethodUi = $this->payment_method;
        $subtotalAmount = (float) collect($cartSnapshot)->sum(
            fn ($item) => ((float) ($item['price'] ?? 0)) * ((int) ($item['quantity'] ?? 0))
        );

        $discountAmount = 0.0;
        $discountId = null;

        if ($this->applied_discount_id) {
            $discount = Discount::query()
                ->where('id', $this->applied_discount_id)
                ->where('is_active', true)
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->when($restaurantId, fn ($query) => $query->where('restaurant_id', $restaurantId))
                ->first();

            if (! $discount) {
                $this->addError('coupon_code', 'El cupón aplicado ya no está disponible. Vuelve a aplicarlo.');

                return;
            }

            $discountId = $discount->id;
            $discountAmount = $discount->type === 'percentage'
                ? ($subtotalAmount * ((float) $discount->value / 100))
                : (float) $discount->value;
            $discountAmount = max(0.0, min($subtotalAmount, $discountAmount));
        }

        $totalAmount = max(0.0, $subtotalAmount - $discountAmount);

        $paymentMethodForSale = match ($payMethodUi) {
            'efectivo' => 'cash',
            'tarjeta' => 'card',
            'transferencia' => 'transfer',
            default => 'cash',
        };

        try {
            DB::transaction(function () use ($user, $restaurantId, $totalAmount, $cartSnapshot, $orderType, $address, $paymentMethodForSale, $discountId, $discountAmount) {

                // 1. Crear el pedido
                $order = Order::create([
                    'status' => 'pending',
                    'type' => $orderType,
                    'table_id' => null,
                    'waiter_id' => null,  // Pedido web: no hay mozo asignado
                    'restaurant_id' => $restaurantId,
                    'delivery_address' => $orderType === 'delivery' ? $address : null,
                    'delivery_phone' => $user->phone,
                    'customer_name' => $user->name,
                    'customer_id' => $user->cliente?->id,
                    'stock_deducted' => false,
                ]);

                // 2. Pivot de productos
                foreach ($cartSnapshot as $productId => $item) {
                    $productId = (int) $productId;
                    $quantity = (int) ($item['quantity'] ?? 0);
                    $price = (float) ($item['price'] ?? 0);

                    if ($quantity <= 0) {
                        continue;
                    }

                    $product = Product::withoutTrashed()->find($productId);

                    if (! $product) {
                        continue;
                    }

                    // Insertar en order_product
                    $order->products()->attach($productId, [
                        'quantity' => $quantity,
                        'price' => $price,
                        'notes' => null,
                    ]);
                }

                // 3. Descontar stock al crear pedido (flujo oficial con listener)
                OrderProcessing::dispatch($order);

                // Asegurar que el pedido web permanezca pendiente al crearse
                if ($order->status !== 'pending') {
                    $order->status = 'pending';
                    $order->saveQuietly();
                }

                // 4. Obtener la caja ABIERTA del día para registrar esta venta
                $openCaja = Caja::query()
                    ->where('restaurant_id', $restaurantId)
                    ->where('status', 'abierta')
                    ->whereDate('opening_date', now()->toDateString())
                    ->latest('opening_date')
                    ->first();

                if (! $openCaja) {
                    throw new \RuntimeException('No hay caja abierta para hoy. Abre caja para registrar ventas web.');
                }

                // 5. Crear la venta (Sale) — impacta en la contabilidad del panel Admin
                $sale = Sale::create([
                    'total_amount' => $totalAmount,
                    'payment_method' => $paymentMethodForSale,
                    'status' => 'paid',
                    'order_id' => $order->id,
                    'cashier_id' => null,  // Pedido web: auto-gestionado
                    'caja_id' => $openCaja->id,

                    'restaurant_id' => $restaurantId,
                ]);

                if ($discountId && $discountAmount > 0) {
                    $sale->discounts()->attach($discountId, [
                        'amount_discounted' => $discountAmount,
                    ]);
                }

                // 4. Guardar dirección para futuras compras
                if ($orderType === 'delivery' && filled($address)) {
                    $user->address = $address;
                    $user->save();
                }
            });
        } catch (\Throwable $e) {
            Log::error('Checkout transaction failed', [
                'user_id' => $user->id,
                'restaurant_id' => $restaurantId,
                'message' => $e->getMessage(),
            ]);

            $this->addError('type', str_contains($e->getMessage(), 'No hay caja abierta')
                ? 'No hay caja abierta para hoy. Solicita al administrador abrir caja e intenta nuevamente.'
                : 'No pudimos procesar tu pedido. Inténtalo nuevamente.');

            return;
        }

        // 5. Limpiar carrito y redirigir
        session()->forget('cart');
        session()->flash('success', '¡Tu pedido fue recibido y pagado! Pronto lo estaremos preparando. 🎉');

        $this->redirect('/', navigate: true);
    }
};

?>

<div class="min-h-screen bg-gray-50 py-6 sm:py-8 md:py-10 overflow-x-hidden">
    <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">

        <div class="mb-4">
            <a
                href="{{ url('/') }}"
                class="inline-flex items-center gap-2 px-3 py-2 sm:px-4 border-2 border-orange-500 text-orange-600 text-sm sm:text-base font-bold rounded-xl hover:bg-orange-50 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500 focus-visible:ring-offset-2"
            >
                <span aria-hidden="true">←</span>
                Volver al Menú
            </a>
        </div>

        <div class="mb-6 sm:mb-8">
            <h1 class="text-2xl sm:text-3xl font-black text-gray-900">Finalizar compra</h1>
            <p class="mt-1 text-sm text-gray-500">Completa los datos para confirmar tu pedido en MOODI.</p>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

            {{-- ============= COLUMNA IZQUIERDA ============= --}}
            <section class="lg:col-span-2 space-y-6">

                {{-- Paso 1: Entrega --}}
                <div class="rounded-2xl border border-gray-100 bg-white p-4 sm:p-6 shadow-sm md:p-8">
                    <div class="flex items-center gap-3 mb-5">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-orange-100 text-orange-600 text-sm font-black">1</span>
                        <div>
                            <h2 class="text-lg font-black text-gray-900">Entrega</h2>
                            <p class="text-xs text-gray-500">¿Cómo querés recibir tu pedido?</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label class="cursor-pointer rounded-xl border-2 p-3 sm:p-4 transition-all {{ $type === 'delivery' ? 'border-orange-500 bg-orange-50' : 'border-gray-200 hover:border-orange-300' }}">
                            <input type="radio" wire:model.live="type" value="delivery" class="sr-only">
                            <div class="flex items-center gap-3">
                                <svg class="h-6 w-6 {{ $type === 'delivery' ? 'text-orange-600' : 'text-gray-400' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v3"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 21H9l-1-4h8l-1 4Z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 13h6M19 10l3 3-3 3"/>
                                </svg>
                                <div>
                                    <span class="block text-sm font-bold text-gray-900">Delivery</span>
                                    <span class="block text-xs text-gray-500 mt-0.5">Te lo llevamos a tu domicilio.</span>
                                </div>
                            </div>
                        </label>

                        <label class="cursor-pointer rounded-xl border-2 p-3 sm:p-4 transition-all {{ $type === 'para_llevar' ? 'border-orange-500 bg-orange-50' : 'border-gray-200 hover:border-orange-300' }}">
                            <input type="radio" wire:model.live="type" value="para_llevar" class="sr-only">
                            <div class="flex items-center gap-3">
                                <svg class="h-6 w-6 {{ $type === 'para_llevar' ? 'text-orange-600' : 'text-gray-400' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 10a4 4 0 0 1-8 0"/>
                                </svg>
                                <div>
                                    <span class="block text-sm font-bold text-gray-900">Retiro en el local</span>
                                    <span class="block text-xs text-gray-500 mt-0.5">Pasás a buscar tu pedido.</span>
                                </div>
                            </div>
                        </label>
                    </div>

                    @if($type === 'delivery')
                        <div class="mt-5">
                            <label for="delivery_address" class="block text-sm font-semibold text-gray-700">
                                Dirección de envío <span class="text-red-500">*</span>
                            </label>
                            <input
                                id="delivery_address"
                                type="text"
                                wire:model="delivery_address"
                                placeholder="Ej: Av. Corrientes 1234, Depto 5B, Buenos Aires"
                                class="mt-1.5 block w-full rounded-xl border-gray-300 focus:border-orange-500 focus:ring-orange-500 text-sm"
                            />
                            @error('delivery_address')
                                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif
                </div>

                {{-- Paso 2: Datos del titular --}}
                <div class="rounded-2xl border border-gray-100 bg-white p-4 sm:p-6 shadow-sm md:p-8">
                    <div class="flex items-center gap-3 mb-5">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-orange-100 text-orange-600 text-sm font-black">2</span>
                        <div>
                            <h2 class="text-lg font-black text-gray-900">Información de contacto</h2>
                            <p class="text-xs text-gray-500">Tus datos para coordinar la entrega.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700">Nombre</label>
                            <input
                                type="text"
                                value="{{ auth()->user()->name }}"
                                readonly
                                class="mt-1.5 block w-full rounded-xl border-gray-200 bg-gray-50 text-gray-600 text-sm cursor-default truncate"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700">Teléfono</label>
                            <input
                                type="text"
                                value="{{ auth()->user()->phone ?: 'Sin teléfono registrado' }}"
                                readonly
                                class="mt-1.5 block w-full rounded-xl border-gray-200 bg-gray-50 text-gray-600 text-sm cursor-default truncate"
                            />
                        </div>
                    </div>
                </div>

                {{-- Paso 3: Pago --}}
                <div class="rounded-2xl border border-gray-100 bg-white p-4 sm:p-6 shadow-sm md:p-8">
                    <div class="flex items-center gap-3 mb-5">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-orange-100 text-orange-600 text-sm font-black">3</span>
                        <div>
                            <h2 class="text-lg font-black text-gray-900">Pago</h2>
                            <p class="text-xs text-gray-500">Seleccioná cómo vas a pagar.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label class="cursor-pointer rounded-xl border-2 p-3 sm:p-4 transition-all {{ $payment_method === 'efectivo' ? 'border-orange-500 bg-orange-50' : 'border-gray-200 hover:border-orange-300' }}">
                            <input type="radio" wire:model.live="payment_method" value="efectivo" class="sr-only">
                            <div class="flex items-center gap-3">
                                <svg class="h-6 w-6 {{ $payment_method === 'efectivo' ? 'text-orange-600' : 'text-gray-400' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5m-18 4.5h15M5.25 3.75h13.5a3 3 0 0 1 3 3v10.5a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3V6.75a3 3 0 0 1 3-3Z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 15.75h1.5"/>
                                </svg>
                                <span class="text-sm font-bold text-gray-900">Efectivo</span>
                            </div>
                        </label>

                        <label class="cursor-pointer rounded-xl border-2 p-3 sm:p-4 transition-all {{ $payment_method === 'tarjeta' ? 'border-orange-500 bg-orange-50' : 'border-gray-200 hover:border-orange-300' }}">
                            <input type="radio" wire:model.live="payment_method" value="tarjeta" class="sr-only">
                            <div class="flex items-center gap-3">
                                <svg class="h-6 w-6 {{ $payment_method === 'tarjeta' ? 'text-orange-600' : 'text-gray-400' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="2" y="5" width="20" height="14" rx="2"/>
                                    <path stroke-linecap="round" d="M2 10h20"/>
                                </svg>
                                <span class="text-sm font-bold text-gray-900">Tarjeta</span>
                            </div>
                        </label>

                        <label class="cursor-pointer rounded-xl border-2 p-3 sm:p-4 transition-all {{ $payment_method === 'transferencia' ? 'border-orange-500 bg-orange-50' : 'border-gray-200 hover:border-orange-300' }}">
                            <input type="radio" wire:model.live="payment_method" value="transferencia" class="sr-only">
                            <div class="flex items-center gap-3">
                                <svg class="h-6 w-6 {{ $payment_method === 'transferencia' ? 'text-orange-600' : 'text-gray-400' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m8 7 4-4 4 4M12 3v14M16 17l-4 4-4-4"/>
                                </svg>
                                <span class="text-sm font-bold text-gray-900">Transferencia / App</span>
                            </div>
                        </label>
                    </div>

                    @if($payment_method === 'efectivo')
                        <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="cash_amount" class="block text-sm font-semibold text-gray-700">
                                    ¿Con cuánto vas a abonar? <span class="text-red-500">*</span>
                                </label>
                                <div class="relative mt-1.5">
                                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-gray-500 text-sm font-bold pointer-events-none">$</span>
                                    <input
                                        id="cash_amount"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        wire:model.live="cash_amount"
                                        placeholder="0"
                                        class="block w-full rounded-xl border-gray-300 pl-7 focus:border-orange-500 focus:ring-orange-500 text-sm"
                                    />
                                </div>
                                @error('cash_amount')
                                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            @if(filled($cash_amount) && (float)$cash_amount > 0)
                                <div class="flex flex-col justify-end pb-1">
                                    <span class="text-xs text-gray-500">Vuelto estimado</span>
                                    <span class="text-2xl font-black {{ $this->change >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                                        ${{ number_format($this->change, 0, ',', '.') }}
                                    </span>
                                    @if($this->change < 0)
                                        <span class="text-xs text-red-500 mt-0.5">El monto es menor al total.</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

            </section>

            {{-- ============= COLUMNA DERECHA (Resumen) ============= --}}
            <aside class="h-fit rounded-2xl border border-gray-100 bg-white p-4 sm:p-6 shadow-sm md:p-8 lg:sticky lg:top-24 min-w-0">
                <h2 class="text-lg font-black text-gray-900">Resumen</h2>

                <div class="mt-4 space-y-2">
                    @foreach($cartItems as $item)
                        <div class="flex items-center justify-between gap-2 sm:gap-3 rounded-xl border border-gray-100 bg-gray-50 p-2.5 sm:p-3 min-w-0">
                            <div class="min-w-0">
                                <p class="truncate text-xs sm:text-sm font-bold text-gray-900">{{ $item['name'] }}</p>
                                <p class="mt-0.5 text-xs text-gray-500">
                                    x{{ $item['quantity'] }} &times; ${{ number_format((float)$item['price'], 0, ',', '.') }}
                                </p>
                            </div>
                            <span class="shrink-0 font-black text-orange-600 text-xs sm:text-sm">
                                ${{ number_format(((float)$item['price']) * ((int)$item['quantity']), 0, ',', '.') }}
                            </span>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 border-t border-gray-100 pt-4">
                    <label for="coupon_code" class="block text-sm font-semibold text-gray-700">Cupón de descuento</label>
                    <div class="mt-2 flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                        <input
                            id="coupon_code"
                            type="text"
                            wire:model="coupon_code"
                            placeholder="Ingresa tu cupón"
                            class="block w-full min-w-0 rounded-xl border-gray-300 focus:border-orange-500 focus:ring-orange-500 text-sm"
                        />
                        <button
                            type="button"
                            wire:click="applyCoupon"
                            class="shrink-0 rounded-xl border border-orange-500 px-3 py-2 text-sm font-bold text-orange-600 hover:bg-orange-50 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500"
                        >
                            Aplicar
                        </button>
                    </div>
                    @error('coupon_code')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mt-4 space-y-2 border-t border-gray-100 pt-4">
                    <div class="flex items-center justify-between text-sm text-gray-500">
                        <span>Subtotal</span>
                        <span>${{ number_format($this->subtotal, 0, ',', '.') }}</span>
                    </div>
                    @if($discount_amount > 0)
                        <div class="flex items-center justify-between text-sm text-emerald-600">
                            <span>Descuento</span>
                            <span>-${{ number_format($discount_amount, 0, ',', '.') }}</span>
                        </div>
                    @endif
                    <div class="flex items-center justify-between text-sm text-gray-500">
                        <span>Envío</span>
                        <span class="font-semibold text-emerald-600">Gratis</span>
                    </div>
                    <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                        <span class="font-bold text-gray-700">Total</span>
                        <span class="text-xl sm:text-2xl font-black text-gray-900 whitespace-nowrap">
                            ${{ number_format($this->total, 0, ',', '.') }}
                        </span>
                    </div>
                </div>

                <button
                    type="button"
                    wire:click="placeOrder"
                    wire:loading.attr="disabled"
                    class="mt-6 w-full rounded-xl bg-orange-500 py-3.5 text-sm font-bold text-white shadow-md shadow-orange-200 hover:bg-orange-600 active:scale-[0.98] transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500 focus-visible:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed"
                >
                    <span wire:loading.remove wire:target="placeOrder" class="flex items-center justify-center gap-2">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m9 12 2 2 4-4"/>
                            <circle cx="12" cy="12" r="9"/>
                        </svg>
                        Confirmar y Pagar
                    </span>
                    <span wire:loading wire:target="placeOrder" class="flex items-center justify-center gap-2">
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
                        </svg>
                        Procesando...
                    </span>
                </button>

                <p class="mt-3 text-center text-xs text-gray-400">
                    Al confirmar aceptás nuestros términos del servicio.
                </p>
            </aside>

        </div>
    </div>
</div>
