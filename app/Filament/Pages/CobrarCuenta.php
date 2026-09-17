<?php

namespace App\Filament\Pages;

use App\Models\Caja;
use App\Models\Discount;
use App\Models\Order;
use App\Models\Sale;
use App\Models\Table;
use App\Services\StockDeductionService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;

/**
 * Punto de Venta de Cobro — Cobrar Cuenta (TPV de cobro estilo Stitch).
 *
 * Muestra la CUENTA completa de una mesa (todas las comandas activas),
 * permite aplicar descuentos activos, elegir método de pago (con vuelto
 * en vivo para efectivo) y registrar las ventas con la MISMA semántica de
 * TableMap::cobrarMesa(): una Sale por comanda, descuento proporcional,
 * status 'completed' y release de la mesa.
 *
 * FIX del hallazgo de stock: al cobrar, si el pedido aún no pasó por
 * 'processing' (stock_deducted=false), se descuenta stock FEFO vía
 * StockDeductionService antes de completar el pedido. El cobro ya no
 * "olvida" descontar stock.
 *
 * Acceso (decisión del dueño 2026-09-17): super_admin, Cajero y Mozo.
 * Cocinero NO.
 */
class CobrarCuenta extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static string $view = 'filament.pages.cobrar-cuenta';

    protected static ?string $title = 'Cobrar Cuenta';

    protected static ?string $navigationLabel = 'Cobrar Cuenta';

    protected static ?string $navigationGroup = 'Operaciones del Salón';

    protected static ?int $navigationSort = 2;

    // Mesa a cobrar: llega por ?table_id= (desde Mapa/CrearPedido) o se
    // elige desde el selector táctil de mesas ocupadas con cuenta abierta.
    public ?int $selectedTableId = null;

    // Valores EXACTOS de payment_method que usa el sistema (cash|card|transfer).
    public string $paymentMethod = 'cash';

    // Descuentos activos seleccionados (ids) — patrón de TableMap.
    public array $selectedDiscounts = [];

    // Monto recibido en efectivo (input grande del TPV).
    public string $montoRecibido = '';

    public static function canAccess(): bool
    {
        // TPV de cobro operativo: super_admin, Cajero y Mozo (decisión del
        // dueño 2026-09-17). Cocinero NO cobra cuentas.
        $user = auth()->user();

        return $user !== null && $user->hasAnyRole(['super_admin', 'Cajero', 'Mozo']);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public function mount(): void
    {
        $tableId = (int) request()->query('table_id', 0);

        if ($tableId > 0) {
            $table = Table::where('restaurant_id', 1)->find($tableId);

            if ($table) {
                $this->selectedTableId = $table->id;
            }
        }
    }

    // ─────────────────────────────────────────────────────────────
    // SELECCIÓN DE MESA
    // ─────────────────────────────────────────────────────────────

    public function selectTable(?int $tableId): void
    {
        $table = $tableId ? Table::where('restaurant_id', 1)->find($tableId) : null;

        $this->selectedTableId = $table?->id;

        $this->resetCobroState();

        // Invalidar el computed de la cuenta para que se recalcule con la
        // mesa nueva en el mismo ciclo de request.
        unset($this->account);
    }

    /**
     * Mesas OCUPADAS con pedidos activos (cuenta pendiente) para el
     * selector táctil cuando no llega ?table_id=.
     */
    #[Computed]
    public function chargeableTables(): Collection
    {
        return Table::where('restaurant_id', 1)
            ->where('status', Table::STATUS_OCCUPIED)
            ->withCount(['orders as active_orders_count' => fn ($query) => $query->whereIn('status', Table::ORDER_OPEN_STATUSES)])
            ->orderBy('number')
            ->get()
            ->filter(fn (Table $table) => (int) $table->active_orders_count > 0)
            ->map(fn (Table $table) => [
                'id' => $table->id,
                'number' => $table->number,
                'zone_label' => $this->zoneLabel($table->location),
                'orders_count' => (int) $table->active_orders_count,
            ])
            ->values();
    }

    /**
     * La CUENTA de la mesa seleccionada: comandas activas con sus ítems
     * (producto × qty, nota y subtotal por línea) + datos del encabezado.
     */
    #[Computed]
    public function account(): ?array
    {
        if (! $this->selectedTableId) {
            return null;
        }

        $table = Table::with([
            'orders' => fn ($query) => $query
                ->whereIn('status', Table::ORDER_OPEN_STATUSES)
                ->with(['orderProducts.product.category', 'waiter'])
                ->orderBy('created_at', 'asc'),
            'waiter',
        ])->where('restaurant_id', 1)->find($this->selectedTableId);

        if (! $table || $table->orders->isEmpty()) {
            return null;
        }

        $orders = $table->orders->map(fn (Order $order) => [
            'id' => $order->id,
            'status' => $order->status,
            'created_time' => $order->created_at->format('H:i'),
            'waiter_name' => $order->waiter?->name,
            'subtotal' => round($order->orderProducts->sum(fn ($item) => (float) $item->quantity * (float) $item->price), 2),
            'items' => $order->orderProducts->map(fn ($item) => [
                'name' => $item->product?->name ?? 'Producto eliminado',
                'quantity' => (int) $item->quantity,
                'price' => (float) $item->price,
                'notes' => $item->notes,
                'subtotal' => round((float) $item->quantity * (float) $item->price, 2),
                'emoji' => self::categoryEmoji($item->product?->category?->name ?? ''),
            ])->values(),
        ]);

        $firstOrder = $table->orders->first();

        return [
            'table_id' => $table->id,
            'number' => $table->number,
            'location' => $table->location,
            'zone_label' => $this->zoneLabel($table->location),
            'status' => $table->status,
            'status_label' => $table->status === Table::STATUS_OCCUPIED ? 'Ocupada' : ucfirst((string) $table->status),
            'waiter_name' => $table->waiter?->name ?? $firstOrder?->waiter?->name ?? 'Sin asignar',
            'elapsed' => $firstOrder?->created_at->diffForHumans(null, true) ?? '—',
            'orders_count' => $table->orders->count(),
            'items_count' => (int) $table->orders->sum(fn (Order $order) => $order->orderProducts->sum('quantity')),
            'orders' => $orders,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // TOTALES Y CÁLCULOS EN VIVO
    // ─────────────────────────────────────────────────────────────

    /**
     * Subtotal de la mesa: suma de quantity * price de TODAS las comandas
     * activas (Order no tiene columna total).
     */
    public function getSubtotalProperty(): float
    {
        if (! $this->account) {
            return 0.0;
        }

        return round(collect($this->account['orders'])->sum('subtotal'), 2);
    }

    /**
     * Descuento total de los descuentos activos seleccionados (patrón de
     * TableMap::updatedSelectedDiscounts): percentage → % del subtotal,
     * fixed → monto fijo.
     */
    public function getDiscountAmountProperty(): float
    {
        if (empty($this->selectedDiscounts)) {
            return 0.0;
        }

        $subtotal = $this->subtotal;
        $totalDiscount = 0.0;

        foreach ($this->selectedDiscounts as $discountId) {
            $discount = Discount::where('id', $discountId)
                ->where('restaurant_id', 1)
                ->where('is_active', true)
                ->first();

            if (! $discount) {
                continue;
            }

            if ($discount->type === 'percentage') {
                $totalDiscount += $subtotal * ((float) $discount->value / 100);
            } else {
                $totalDiscount += (float) $discount->value;
            }
        }

        return round($totalDiscount, 2);
    }

    /**
     * TOTAL final de la cuenta: subtotal − descuento (nunca negativo).
     */
    public function getTotalProperty(): float
    {
        return round(max(0, $this->subtotal - $this->discountAmount), 2);
    }

    /**
     * Vuelto calculado EN VIVO para efectivo: recibido − total (solo si
     * recibido >= total; si no, 0 y se bloquea el cobro).
     */
    public function getVueltoProperty(): float
    {
        if ($this->paymentMethod !== 'cash') {
            return 0.0;
        }

        $received = $this->receivedAmount();

        if ($received < $this->total) {
            return 0.0;
        }

        return round($received - $this->total, 2);
    }

    /**
     * Efectivo recibido parseado (acepta coma decimal del teclado argentino).
     */
    public function receivedAmount(): float
    {
        return (float) str_replace(',', '.', trim((string) $this->montoRecibido));
    }

    /**
     * ¿Se puede cobrar? Efectivo exige monton recibido >= total; tarjeta y
     * transferencia no requieren monto recibido.
     */
    public function canCobrar(): bool
    {
        if (! $this->account) {
            return false;
        }

        if ($this->paymentMethod === 'cash') {
            return $this->receivedAmount() >= $this->total;
        }

        return true;
    }

    /**
     * ¿Hay advertencia de monto insuficiente? (efectivo con recibido < total).
     */
    public function cashInsufficient(): bool
    {
        if ($this->paymentMethod !== 'cash') {
            return false;
        }

        $received = $this->receivedAmount();

        return $received > 0 && $received < $this->total;
    }

    /**
     * Descuentos activos del restaurante para el selector (patrón TableMap).
     */
    public function getActiveDiscountsProperty(): Collection
    {
        return Discount::where('restaurant_id', 1)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function paymentMethodLabel(): string
    {
        return match ($this->paymentMethod) {
            'cash' => 'Efectivo',
            'card' => 'Tarjeta',
            'transfer' => 'Transferencia',
            default => ucfirst((string) $this->paymentMethod),
        };
    }

    // ─────────────────────────────────────────────────────────────
    // COBRO (transacción principal)
    // ─────────────────────────────────────────────────────────────

    /**
     * Cobrar la cuenta de la mesa.
     *
     * Semántica idéntica a TableMap::cobrarMesa(): una Sale por comanda,
     * descuento proporcional al subtotal de cada pedido, orders → completed,
     * mesa release con la máquina de estados. ADEMÁS descuenta stock FEFO
     * (StockDeductionService) de cada pedido que aún no lo tenga descontado.
     */
    public function cobrarCuenta(): void
    {
        // Defensa en profundidad: re-validar el rol dentro del método
        // (decisión del dueño 2026-09-17: super_admin, Cajero y Mozo).
        abort_unless(
            auth()->user()?->hasAnyRole(['super_admin', 'Cajero', 'Mozo']),
            403
        );

        if (! $this->selectedTableId) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('Seleccioná una mesa para cobrar.')
                ->send();

            return;
        }

        if (! in_array($this->paymentMethod, ['cash', 'card', 'transfer'], true)) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('Método de pago inválido.')
                ->send();

            return;
        }

        $table = Table::with(['orders' => function ($query) {
            $query->whereIn('status', Table::ORDER_OPEN_STATUSES)
                ->with('orderProducts');
        }])->where('restaurant_id', 1)->find($this->selectedTableId);

        if (! $table || $table->orders->isEmpty()) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('No hay pedidos activos para cobrar en esta mesa.')
                ->send();

            return;
        }

        // Verificar que haya una caja abierta (mismo chequeo que TableMap).
        $cajaAbierta = Caja::where('restaurant_id', 1)
            ->where('status', 'abierta')
            ->first();

        if (! $cajaAbierta) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('No hay una caja abierta. Abre una caja antes de registrar ventas.')
                ->send();

            return;
        }

        $subtotal = $this->subtotal;
        $discountAmount = $this->discountAmount;

        // Efectivo: el monto recibido debe cubrir el total (server-side).
        if ($this->paymentMethod === 'cash' && $this->receivedAmount() < $this->total) {
            Notification::make()
                ->warning()
                ->title('Monto insuficiente')
                ->body('El monto recibido es menor al total a cobrar.')
                ->send();

            return;
        }

        try {
            $tableNumber = $table->number;

            DB::transaction(function () use ($table, $cajaAbierta, $subtotal, $discountAmount) {
                // Crear una venta por cada pedido activo de la mesa.
                foreach ($table->orders as $order) {
                    // FIX stock: si el pedido nunca pasó por 'processing'
                    // (stock_deducted=false), descontar stock FEFO AHORA,
                    // antes de marcar completed.
                    if (! $order->stock_deducted) {
                        app(StockDeductionService::class)->deductForOrder($order);
                    }

                    // Total del pedido individual: Σ quantity * price.
                    $orderTotal = $order->orderProducts->sum(function ($item) {
                        return (float) $item->quantity * (float) $item->price;
                    });

                    // Proporción de descuento para este pedido (patrón TableMap).
                    $proportionalDiscount = $subtotal > 0
                        ? ($orderTotal / $subtotal) * $discountAmount
                        : 0;

                    $orderFinalTotal = max(0, $orderTotal - $proportionalDiscount);

                    // Crear la venta (una por comanda).
                    $sale = Sale::create([
                        'order_id' => $order->id,
                        'restaurant_id' => 1,
                        'caja_id' => $cajaAbierta->id,
                        'cashier_id' => Auth::id(),
                        'total_amount' => $orderFinalTotal,
                        'payment_method' => $this->paymentMethod,
                        'status' => 'paid',
                    ]);

                    // Asociar descuentos con el mismo reparto que TableMap.
                    if (! empty($this->selectedDiscounts)) {
                        foreach ($this->selectedDiscounts as $discountId) {
                            $discount = Discount::where('id', $discountId)
                                ->where('restaurant_id', 1)
                                ->where('is_active', true)
                                ->first();

                            if ($discount) {
                                $discountValue = $discount->type === 'percentage'
                                    ? $orderTotal * ((float) $discount->value / 100)
                                    : ($orderTotal / $subtotal) * (float) $discount->value;

                                $sale->discounts()->attach($discountId, [
                                    'amount_discounted' => $discountValue,
                                ]);
                            }
                        }
                    }

                    // El pedido cobrado deja de estar activo: pasa a 'completed'.
                    $order->update(['status' => 'completed']);
                }

                // Liberar la mesa con la máquina de estados centralizada
                // (release: occupied → available o reserved si hay reservas).
                $table->release();
            });

            Notification::make()
                ->success()
                ->title('Cuenta Cobrada')
                ->body("Se cobró la cuenta de la mesa **#{$tableNumber}** correctamente.")
                ->send();

            $this->resetPanel();
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Error al procesar el cobro')
                ->body($e->getMessage())
                ->persistent()
                ->send();
        }
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────

    /**
     * Normalizar la location de la mesa a la etiqueta de zona visible.
     */
    private function zoneLabel(?string $location): string
    {
        $key = strtolower(Str::ascii(trim((string) $location)));

        return match ($key) {
            'terraza', 'exterior', 'patio' => 'Terraza',
            'barra', 'bar' => 'Barra',
            default => 'Salón',
        };
    }

    public static function categoryEmoji(string $categoryName): string
    {
        $name = mb_strtolower($categoryName);

        return match (true) {
            str_contains($name, 'hamburg') => '🍔',
            str_contains($name, 'papa') => '🍟',
            str_contains($name, 'bebida'), str_contains($name, 'cerveza') => '🥤',
            str_contains($name, 'postre') => '🍰',
            str_contains($name, 'temporal') => '✨',
            str_contains($name, 'entrada'), str_contains($name, 'picada') => '🥗',
            default => '🍽️',
        };
    }

    public static function money(float|int|string $value): string
    {
        return number_format((float) $value, 2, ',', '.');
    }

    /**
     * Reset del estado del cobro conservando la mesa seleccionada.
     */
    private function resetCobroState(): void
    {
        $this->paymentMethod = 'cash';
        $this->selectedDiscounts = [];
        $this->montoRecibido = '';
    }

    /**
     * Reset completo del panel (post-cobro exitoso): vuelve al selector.
     */
    private function resetPanel(): void
    {
        $this->selectedTableId = null;
        $this->resetCobroState();
        unset($this->account);
    }
}