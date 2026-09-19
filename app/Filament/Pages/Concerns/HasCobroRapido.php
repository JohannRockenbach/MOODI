<?php

namespace App\Filament\Pages\Concerns;

use App\Models\Caja;
use App\Models\Discount;
use App\Models\Order;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Table;
use App\Services\StockDeductionService;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

/**
 * Modal RÁPIDO de cobro EN LA MISMA página (Livewire 3, overlay custom estilo
 * Stitch). Compartido por CrearPedido (TPV) y TableMap (Mapa de Mesas): ambos
 * incluyen la vista `filament.modals.cobro-cuenta-modal` y usan este trait
 * para TODO el estado y la lógica del cobro (sin dispatch entre componentes).
 *
 * Muestra SIEMPRE los datos reales de lo que se cobra: mesa o pedido para
 * llevar, incluida la comanda EN CURSO (draft del carrito del TPV).
 *
 * Modelo: sales.order_id es NOT NULL + UNIQUE (una Sale por Order) → para
 * cobrar una comanda en curso hay que CREAR el Order dentro de la transacción
 * de cobro (con los PRECIOS REALES de la DB re-validados por ítem).
 *
 * F2: cada página dispara el LISTENER global 'solicitar-cobro' (script en el
 * blade); el trait lo traduce a abrirCobro() con el contexto real de la página.
 *
 * Acceso (decisión del dueño 2026-09-17): super_admin, Cajero y Mozo.
 * Cocinero NO — defensa en profundidad dentro de cobrar().
 */
trait HasCobroRapido
{
    public bool $open = false;

    /**
     * Modo del modal de cobro (tabs táctiles superiores):
     * - 'nuevo': cobra la comanda en curso (draft del TPV).
     * - 'existentes': cobra una mesa con cuenta o un pedido para llevar ya
     *   creado (lista visible cuando no hay selección).
     * El draft del TPV SOLO se cobra en modo 'nuevo'.
     */
    public string $cobroMode = 'existentes';

    // Mesa a cobrar (comandas enviadas de la mesa).
    public ?int $selectedTableId = null;

    // Pedido PARA LLEVAR ya creado (enviado) a cobrar.
    public ?int $selectedOrderId = null;

    // Comanda en curso SIN enviar: [product_id, name, price, qty, note, section_key, section_label].
    public array $draft = [];

    // Nota general a cocina de la comanda en curso (orders.notes).
    public string $draftKitchenNote = '';

    // Modalidad del draft: 'salon' | 'para_llevar'.
    public string $orderType = 'salon';

    // Método por defecto de la primera fila del SPLIT (cash|card|transfer).
    public string $paymentMethod = 'cash';

    // Descuentos activos seleccionados (ids) — patrón de TableMap.
    public array $selectedDiscounts = [];

    // Monto recibido en efectivo (input grande del TPV).
    public string $montoRecibido = '';

    // SPLIT de pago: filas ['method' => cash|card|transfer, 'amount' => '...']
    // (hasta 3). Por defecto UNA fila con [método = paymentMethod, monto = total].
    public array $payments = [];

    // ─────────────────────────────────────────────────────────────
    // APERTURA / CIERRE
    // ─────────────────────────────────────────────────────────────

    /**
     * Abrir el modal de cobro EN LA MISMA página con el contexto real.
     *
     * Método PÚBLICO de la página (NO es un evento). Recibe payload opcional
     * por parámetros; cuando se invoca sin argumentos (F2 / botón COBRAR del
     * TPV) deriva el contexto del propio estado de la página: la mesa, la
     * modalidad, el carrito en curso (draft) y la nota a cocina.
     *
     * - CrearPedido: wire:click="abrirCobro" (sin args) → usa items/orderType/
     *   kitchenNote/selectedTableId del TPV.
     * - TableMap: wire:click="abrirCobro($id)" → cierra sobre la mesa elegida,
     *   sin draft (la comanda en curso vive en el TPV, no en el mapa).
     */
    public function abrirCobro(
        ?int $tableId = null,
        ?int $orderId = null,
        array $draft = [],
        ?string $orderType = null,
        ?string $kitchenNote = null,
    ): void {
        $this->selectedTableId = $this->validId($tableId ?? $this->selectedTableId);
        $this->selectedOrderId = $this->validId($orderId);

        $pageDraft = $draft !== [] ? $draft : $this->pageDraft();
        $this->draft = array_values($pageDraft);

        $this->draftKitchenNote = $kitchenNote ?? $this->pageKitchenNote();

        $type = $orderType ?? $this->pageOrderType();
        $this->orderType = in_array($type, ['salon', 'para_llevar'], true) ? $type : 'salon';

        // Modo inicial del modal:
        // - draft no vacío → 'nuevo' (cobrar la comanda en curso del TPV).
        // - orderId (takeaway) / tableId sin draft / sin contexto → 'existentes'.
        $this->cobroMode = ! empty($this->draft) ? 'nuevo' : 'existentes';

        // Invalidar computeds ANTES de re-inicializar el estado de pago (que
        // lee el total FRESCO de la cuenta recién armada).
        unset($this->account, $this->chargeableTables, $this->takeawayOrders);

        $this->resetCobroState();
        $this->open = true;
    }

    /**
     * Cambiar la pestaña del modal (Nuevo pedido / Existentes). Valida el
     * valor y re-invalida los computeds dependientes para que la cuenta y el
     * total se recalculen contra el modo activo.
     */
    public function setCobroMode(string $mode): void
    {
        if (! in_array($mode, ['nuevo', 'existentes'], true)) {
            return;
        }

        $this->cobroMode = $mode;

        unset($this->account, $this->chargeableTables, $this->takeawayOrders);

        // Re-sincronizar la fila única del split con el total fresco del modo.
        $this->syncSinglePaymentRow();
    }

    /**
     * F2 (escucha el evento global que dispara el script del blade): abre el
     * modal con el contexto actual de la página.
     */
    #[On('solicitar-cobro')]
    public function solicitarCobro(): void
    {
        $this->abrirCobro();
    }

    /**
     * Re-abrir el modal manteniendo el contexto del bloque de cuenta actual
     * (usado desde el selector interno al elegir cuenta).
     *
     * Prefijo selectAccount* (NO selectTable/selectOrder): CrearPedido y
     * TableMap definen SUS propios selectTable() (flujo de la página) que
     * opacan al del trait; el selector interno del modal NO debe colisionar
     * con ellos (si colisiona no resetea payments/selectedOrderId y el cobro
     * queda bloqueado con la fila de pago en '0').
     */
    public function selectAccountTable(?int $tableId): void
    {
        // Elegir una cuenta existente siempre vive en la pestaña Existentes.
        $this->cobroMode = 'existentes';

        $this->selectedTableId = $tableId;
        $this->selectedOrderId = null;
        unset($this->account);
        $this->resetCobroState();
    }

    public function selectAccountOrder(?int $orderId): void
    {
        // Elegir una cuenta existente siempre vive en la pestaña Existentes.
        $this->cobroMode = 'existentes';

        $this->selectedOrderId = $orderId;
        $this->selectedTableId = null;
        unset($this->account);
        $this->resetCobroState();
    }

    /**
     * Cancelar el modal: cierra sin cobrar nada (el draft del padre queda intacto).
     */
    public function cancelar(): void
    {
        $this->open = false;
    }

    // ─────────────────────────────────────────────────────────────
    // SPLIT DE PAGO
    // ─────────────────────────────────────────────────────────────

    /**
     * Agregar una fila de método de pago al split (máximo 3).
     */
    public function addPaymentRow(): void
    {
        if (count($this->payments) >= 3) {
            return;
        }

        $this->payments[] = [
            'method' => $this->paymentMethod,
            'amount' => '',
        ];
    }

    /**
     * Quitar una fila del split. Siempre queda al menos UNA fila.
     */
    public function removePaymentRow(int $index): void
    {
        if (count($this->payments) <= 1 || ! isset($this->payments[$index])) {
            return;
        }

        unset($this->payments[$index]);
        $this->payments = array_values($this->payments);
    }

    /**
     * Toggle de un descuento activo desde el modal. Reemplaza al hook
     * updatedSelectedDiscounts (que TableMap define con lógica legacy y opaca
     * al trait): el método es la ÚNICA fuente de verdad para ambas páginas y
     * re-sincroniza la fila única del split con el nuevo total.
     */
    public function toggleDiscount(int $discountId): void
    {
        $this->selectedDiscounts = in_array($discountId, $this->selectedDiscounts, true)
            ? array_values(array_diff($this->selectedDiscounts, [$discountId]))
            : array_merge($this->selectedDiscounts, [$discountId]);

        $this->syncSinglePaymentRow();
    }

    /**
     * Sin SPLIT (una sola fila con todo el total) el monto SIGUE al total:
     * si el total cambió (descuento), la fila se re-sincroniza sola.
     */
    private function syncSinglePaymentRow(): void
    {
        if (count($this->payments) === 1) {
            $this->payments[0]['amount'] = $this->formatAmountInput($this->accountTotal());
        }
    }

    /**
     * Suma de los montos de TODAS las filas del split (acepta coma decimal).
     */
    public function getPaymentsTotalProperty(): float
    {
        return round(collect($this->payments)
            ->sum(fn (array $row) => $this->parseAmount($row['amount'] ?? '')), 2);
    }

    /**
     * ¿El split incluye efectivo? (controla el bloque "Monto recibido" + vuelto).
     */
    public function hasCashPayment(): bool
    {
        return collect($this->payments)
            ->contains(fn (array $row) => ($row['method'] ?? '') === 'cash' && $this->parseAmount($row['amount'] ?? '') > 0);
    }

    // ─────────────────────────────────────────────────────────────
    // CUENTA (estructura de datos de lo que se cobra)
    // ─────────────────────────────────────────────────────────────

    /**
     * La CUENTA a cobrar según el modo activo del modal:
     *
     * Modo 'nuevo' (comanda en curso del TPV):
     * 1. Comanda en curso (draft) → bloque "Comanda en curso" + (si mesa) las
     *    comandas enviadas de la mesa ("Comandas enviadas").
     * 2. Sin draft → null (el blade muestra el aviso de armar la comanda).
     *
     * Modo 'existentes' (mesa con cuenta / pedido para llevar ya creado):
     * el draft del TPV se IGNORA por completo aunque venga en el payload:
     * 1. selectedOrderId → pedido para llevar ya creado.
     * 2. selectedTableId → comandas enviadas de la mesa.
     * 3. Nada → null (el blade muestra el selector interno de cuentas).
     */
    #[Computed]
    public function account(): ?array
    {
        if ($this->cobroMode === 'nuevo') {
            if (! empty($this->draft)) {
                return $this->buildDraftAccount();
            }

            return null;
        }

        if ($this->selectedOrderId) {
            return $this->buildTakeawayAccount();
        }

        if (! $this->selectedTableId) {
            return null;
        }

        return $this->buildTableAccount();
    }

    /**
     * Cuenta con comanda en curso (draft). El draft se muestra SIEMPRE como
     * bloque "Comanda en curso"; si es de salón se suma a las comandas ya
     * enviadas de la mesa; si es para llevar, la cuenta es el draft solo.
     */
    private function buildDraftAccount(): array
    {
        $draftBlock = $this->buildDraftBlock();
        $isTakeaway = $this->orderType === 'para_llevar' || ! $this->selectedTableId;

        if ($isTakeaway) {
            return $this->takeawayLikeAccount(
                displayLabel: 'Comanda en curso',
                orders: collect([$draftBlock]),
            );
        }

        $table = Table::with([
            'orders' => fn ($query) => $query
                ->whereIn('status', Table::ORDER_OPEN_STATUSES)
                ->with(['orderProducts.product.category', 'waiter'])
                ->orderBy('created_at', 'asc'),
            'waiter',
        ])->where('restaurant_id', 1)->find($this->selectedTableId);

        if (! $table) {
            // La mesa desapareció (liberada/cancelada): la comanda en curso
            // sigue cobrable como cuenta sin mesa.
            return $this->takeawayLikeAccount(
                displayLabel: 'Comanda en curso',
                orders: collect([$draftBlock]),
            );
        }

        $sentOrders = $table->orders->map(fn (Order $order) => $this->mapOrder($order));

        return [
            'table_id' => $table->id,
            'number' => $table->number,
            'location' => $table->location,
            'zone_label' => $this->zoneLabel($table->location),
            'status' => $table->status,
            'status_label' => $table->status === Table::STATUS_OCCUPIED ? 'Ocupada' : ucfirst((string) $table->status),
            'waiter_name' => Auth::user()?->name ?? $table->waiter?->name ?? 'Sin asignar',
            'elapsed' => $table->orders->first()?->created_at->diffForHumans(null, true) ?? '—',
            'orders_count' => $sentOrders->count() + 1, // draft + enviadas
            'items_count' => $draftBlock['items']->sum('quantity') + $sentOrders->sum(fn (array $o) => $o['items']->sum('quantity')),
            'has_draft' => true,
            'orders' => collect([$draftBlock])->concat($sentOrders),
        ];
    }

    /**
     * Cuenta de un PEDIDO PARA LLEVAR ya creado (sin mesa, sin release): un
     * único pedido abierto sin venta. El descuento es DIRECTO (subtotal ==
     * total del pedido único, el reparto degenera en directo).
     */
    private function buildTakeawayAccount(): ?array
    {
        $order = Order::with(['orderProducts.product.category', 'waiter'])
            ->where('restaurant_id', 1)
            ->where('type', 'para_llevar')
            ->whereIn('status', Table::ORDER_OPEN_STATUSES)
            ->whereDoesntHave('sale')
            ->find($this->selectedOrderId);

        if (! $order) {
            return null;
        }

        return $this->takeawayLikeAccount(
            displayLabel: 'Pedido #'.$order->id,
            orders: collect([$this->mapOrder($order)]),
        );
    }

    /**
     * Cuenta de MESA sin draft: comandas enviadas de la mesa.
     */
    private function buildTableAccount(): ?array
    {
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

        $orders = $table->orders->map(fn (Order $order) => $this->mapOrder($order));

        return [
            'table_id' => $table->id,
            'number' => $table->number,
            'location' => $table->location,
            'zone_label' => $this->zoneLabel($table->location),
            'status' => $table->status,
            'status_label' => $table->status === Table::STATUS_OCCUPIED ? 'Ocupada' : ucfirst((string) $table->status),
            'waiter_name' => $table->waiter?->name ?? $table->orders->first()?->waiter?->name ?? 'Sin asignar',
            'elapsed' => $table->orders->first()?->created_at->diffForHumans(null, true) ?? '—',
            'orders_count' => $orders->count(),
            'items_count' => (int) $orders->sum(fn (array $o) => $o['items']->sum('quantity')),
            'has_draft' => false,
            'orders' => $orders,
        ];
    }

    /**
     * Estructura base de una cuenta SIN mesa (para llevar / draft takeaway).
     */
    private function takeawayLikeAccount(string $displayLabel, Collection $orders): array
    {
        return [
            'is_takeaway' => true,
            'order_id' => $this->selectedOrderId,
            'number' => null,
            'display_label' => $displayLabel,
            'location' => 'Para llevar',
            'location_label' => 'Para llevar',
            'zone_label' => 'Para llevar',
            'status_label' => 'Para llevar',
            'waiter_name' => $orders->first()['waiter_name'] ?? Auth::user()?->name ?? 'Sin asignar',
            'elapsed' => '—',
            'orders_count' => $orders->count(),
            'items_count' => (int) $orders->sum(fn (array $o) => $o['items']->sum('quantity')),
            'has_draft' => $orders->contains(fn (array $o) => $o['is_draft'] ?? false),
            'orders' => $orders,
        ];
    }

    /**
     * Bloque "Comanda en curso" (draft). El precio mostrado y el que se cobra
     * es el PRECIO REAL de la DB (nunca se confía en el precio del cliente);
     * si el producto ya no existe se conserva el dato del carrito para
     * mostrarlo, pero el cobro lo rechaza.
     */
    private function buildDraftBlock(): array
    {
        $items = collect($this->draft)->map(function (array $item): array {
            $product = Product::where('restaurant_id', 1)->find($item['product_id'] ?? null);
            $price = $product ? (float) $product->price : (float) ($item['price'] ?? 0);
            $qty = (int) ($item['qty'] ?? 0);

            return [
                'name' => $product?->name ?? ($item['name'] ?? 'Producto eliminado'),
                'quantity' => $qty,
                'price' => $price,
                'notes' => trim((string) ($item['note'] ?? '')),
                'subtotal' => round($price * $qty, 2),
                'emoji' => self::draftEmoji((string) ($item['section_key'] ?? 'otros')),
                'is_draft' => true,
            ];
        })->filter(fn (array $item) => $item['quantity'] > 0)->values();

        return [
            'id' => null,
            'is_draft' => true,
            'status' => 'draft',
            'created_time' => now()->format('H:i'),
            'waiter_name' => Auth::user()?->name ?? 'Sin asignar',
            'subtotal' => round($items->sum('subtotal'), 2),
            'items' => $items,
        ];
    }

    /**
     * Mapear una comanda (Order) al bloque de ítems de la cuenta.
     */
    private function mapOrder(Order $order): array
    {
        return [
            'id' => $order->id,
            'is_draft' => false,
            'status' => $order->status,
            'created_time' => $order->created_at->format('H:i'),
            'waiter_name' => $order->waiter?->name,
            'subtotal' => round($order->orderProducts->sum(fn ($item) => (float) $item->quantity * (float) $item->price), 2),
            'items' => $order->orderProducts->map(fn ($item) => [
                'name' => $item->product?->name ?? 'Producto eliminado',
                'quantity' => (int) $item->quantity,
                'price' => (float) $item->price,
                'notes' => (string) $item->notes,
                'subtotal' => round((float) $item->quantity * (float) $item->price, 2),
                'emoji' => self::categoryEmoji($item->product?->category?->name ?? ''),
            ])->values(),
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // SELECTOR INTERNO (cuando el modal abre sin mesa/pedido/draft)
    // ─────────────────────────────────────────────────────────────

    /**
     * Mesas OCUPADAS con pedidos activos (cuenta pendiente) para el selector.
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
     * Pedidos PARA LLEVAR pendientes de cobro: tipo 'para_llevar', estado
     * abierto y SIN venta asociada.
     */
    #[Computed]
    public function takeawayOrders(): Collection
    {
        return Order::with(['orderProducts.product.category', 'waiter'])
            ->where('restaurant_id', 1)
            ->where('type', 'para_llevar')
            ->whereIn('status', Table::ORDER_OPEN_STATUSES)
            ->whereDoesntHave('sale')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'created_time' => $order->created_at->format('H:i'),
                'waiter_name' => $order->waiter?->name,
                'items_count' => (int) $order->orderProducts->sum('quantity'),
                'subtotal' => round($order->orderProducts->sum(fn ($item) => (float) $item->quantity * (float) $item->price), 2),
            ])
            ->values();
    }

    // ─────────────────────────────────────────────────────────────
    // TOTALES Y CÁLCULOS EN VIVO
    // ─────────────────────────────────────────────────────────────

    /**
     * Subtotal de la CUENTA: suma de quantity * price de TODOS los bloques
     * (comanda en curso + comandas enviadas o pedido para llevar).
     *
     * Accesor EXPLÍCITO (no getSubtotalProperty): CrearPedido ya define su
     * propio getSubtotalProperty (subtotal del CARRITO del TPV), que opaca al
     * del trait — una propiedad/computed de la clase gana siempre sobre el
     * trait. Toda la lógica del modal y el blade usan ESTE método en ambas
     * páginas.
     */
    public function accountSubtotal(): float
    {
        if (! $this->account) {
            return 0.0;
        }

        return round(collect($this->account['orders'])->sum('subtotal'), 2);
    }

    /**
     * Total de la CUENTA (subtotal − descuento). Mismo accesor EXPLÍCITO que
     * accountSubtotal(): CrearPedido define getTotalProperty (total del
     * carrito) y opaca al computed del trait.
     */
    public function accountTotal(): float
    {
        return round(max(0, $this->accountSubtotal() - $this->discountAmountValue()), 2);
    }

    /**
     * Descuento total de los descuentos activos seleccionados: percentage →
     * % del subtotal, fixed → monto fijo.
     */
    public function getDiscountAmountProperty(): float
    {
        return $this->discountAmountValue();
    }

    /**
     * Valor único del descuento. Separado del computed porque TableMap tiene
     * un prop legacy $discountAmount que opaca al computed (una propiedad
     * pública real siempre gana sobre el magic __get de Livewire): toda la
     * lógica interna y el blade usan ESTE método para ser idénticos en ambas
     * páginas.
     */
    public function discountAmountValue(): float
    {
        if (empty($this->selectedDiscounts)) {
            return 0.0;
        }

        $subtotal = $this->accountSubtotal();
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

    public function getTotalProperty(): float
    {
        // Compatibilidad: en páginas sin getter propio (TableMap) $this->total
        // resuelve al total de la CUENTA. En CrearPedido queda opacado por el
        // getter del carrito (por eso el modal usa accountTotal()).
        return $this->accountTotal();
    }

    /**
     * Vuelto en efectivo. Con SPLIT el vuelto se calcula contra el TOTAL de
     * las filas de efectivo (recibido − montoCashTotal), nunca contra la
     * cuenta completa (las otras filas se pagan por otro método).
     */
    public function getVueltoProperty(): float
    {
        $cashTotal = $this->cashRowsTotal();

        if ($cashTotal <= 0) {
            return 0.0;
        }

        $received = $this->receivedAmount();

        if ($received < $cashTotal) {
            return 0.0;
        }

        return round($received - $cashTotal, 2);
    }

    public function receivedAmount(): float
    {
        return $this->parseAmount($this->montoRecibido);
    }

    /**
     * ¿Se puede cobrar? Exige: cuenta armada, filas no vacías con montos > 0,
     * suma del split == total (±0.01) y, si hay efectivo, recibido >= efectivo.
     */
    public function canCobrar(): bool
    {
        if (! $this->account || empty($this->payments)) {
            return false;
        }

        foreach ($this->payments as $row) {
            if (! in_array($row['method'] ?? '', ['cash', 'card', 'transfer'], true)) {
                return false;
            }

            if ($this->parseAmount($row['amount'] ?? '') <= 0) {
                return false;
            }
        }

        if (abs($this->paymentsTotal - $this->accountTotal()) > 0.01) {
            return false;
        }

        $cashTotal = $this->cashRowsTotal();

        if ($cashTotal > 0 && $this->receivedAmount() < $cashTotal) {
            return false;
        }

        return true;
    }

    public function cashInsufficient(): bool
    {
        $cashTotal = $this->cashRowsTotal();

        if ($cashTotal <= 0) {
            return false;
        }

        $received = $this->receivedAmount();

        return $received > 0 && $received < $cashTotal;
    }

    #[Computed]
    public function activeDiscounts(): Collection
    {
        return Discount::where('restaurant_id', 1)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Etiqueta legible del método/pago para el footer del modal: una fila →
     * label del método; varias → "Split · Efectivo + Tarjeta".
     */
    public function paymentMethodLabel(): string
    {
        $rows = collect($this->payments)
            ->filter(fn (array $row) => $this->parseAmount($row['amount'] ?? '') > 0)
            ->values();

        if ($rows->isEmpty()) {
            return $this->methodLabel($this->paymentMethod);
        }

        if ($rows->count() === 1) {
            return $this->methodLabel((string) $rows->first()['method']);
        }

        return 'Split · '.$rows
            ->pluck('method')
            ->map(fn ($method) => $this->methodLabel((string) $method))
            ->unique()
            ->implode(' + ');
    }

    // ─────────────────────────────────────────────────────────────
    // COBRO (transacción principal)
    // ─────────────────────────────────────────────────────────────

    /**
     * Cobrar la cuenta: comanda en curso (crea el Order dentro de la
     * transacción) + comandas enviadas de la mesa, o pedido para llevar.
     *
     * Mesa: una Sale por comanda (draft incluido), descuento proporcional,
     * orders enviadas → completed y release de la mesa. Para llevar: descuento
     * DIRECTO, sin release. En ambos casos stock FEFO (StockDeductionService,
     * idempotente) solo si ! $order->stock_deducted.
     *
     * SPLIT: la Sale se registra SIEMPRE (status paid) y su desglose
     * (sale_payments) reparte los métodos proporcionalmente al subtotal de
     * cada comanda; payment_method de la Sale = método dominante.
     */
    public function cobrar(): void
    {
        // Defensa en profundidad: re-validar el rol dentro del método
        // (decisión del dueño 2026-09-17: super_admin, Cajero y Mozo).
        abort_unless(
            auth()->user()?->hasAnyRole(['super_admin', 'Cajero', 'Mozo']),
            403
        );

        // Defensa: sin cuenta cobrable en el modo activo. En 'existentes' el
        // draft del TPV se IGNORA (solo mesa seleccionada o pedido para
        // llevar); en 'nuevo' el draft es la cuenta.
        $hasChargeableSelection = $this->selectedTableId !== null || $this->selectedOrderId !== null;
        $hasDraftToCharge = $this->cobroMode === 'nuevo' && ! empty($this->draft);

        if (! $hasChargeableSelection && ! $hasDraftToCharge) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('Seleccioná una mesa o un pedido para llevar para cobrar.')
                ->send();

            return;
        }

        // Validación del SPLIT (server-side: no depende del botón disabled).
        if (! $this->paymentsAreValid($this->payments)) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('Método de pago o monto inválido en el split.')
                ->send();

            return;
        }

        if (abs($this->paymentsTotal - $this->accountTotal()) > 0.01) {
            Notification::make()
                ->warning()
                ->title('Monto de pago incorrecto')
                ->body('La suma de los métodos de pago no coincide con el total a cobrar.')
                ->send();

            return;
        }

        // Efectivo: el monto recibido debe cubrir SOLO el total de las filas
        // de efectivo del split (server-side).
        $cashTotal = $this->cashRowsTotal();

        if ($cashTotal > 0 && $this->receivedAmount() < $cashTotal) {
            Notification::make()
                ->warning()
                ->title('Monto insuficiente')
                ->body('El monto recibido en efectivo es menor al total en efectivo del cobro.')
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

        $subtotal = $this->accountSubtotal();
        $discountAmount = $this->discountAmountValue();

        try {
            DB::transaction(function () use ($cajaAbierta, $subtotal, $discountAmount) {
                $ordersToCharge = collect();
                $table = null;
                $draftOrderIds = [];

                // 1) Pedido para llevar ya creado (carrera de terminales cubierta).
                if ($this->selectedOrderId) {
                    $order = Order::with('orderProducts')
                        ->where('restaurant_id', 1)
                        ->where('type', 'para_llevar')
                        ->whereIn('status', Table::ORDER_OPEN_STATUSES)
                        ->whereDoesntHave('sale')
                        ->find($this->selectedOrderId);

                    if (! $order) {
                        throw new \DomainException('El pedido para llevar ya no está disponible para cobrar.');
                    }

                    $ordersToCharge->push($order);
                } elseif ($this->selectedTableId) {
                    // Comandas ENVIADAS de la mesa: se cargan ANTES de crear el
                    // Order del draft para que el draft (recién creado, status
                    // 'pending') no quede incluido y se cobre dos veces.
                    $table = Table::with(['orders' => function ($query) {
                        $query->whereIn('status', Table::ORDER_OPEN_STATUSES)
                            ->with('orderProducts');
                    }])->where('restaurant_id', 1)->find($this->selectedTableId);

                    if (! $table) {
                        throw new \DomainException('No hay pedidos activos para cobrar en esta mesa.');
                    }

                    if ($table->orders->isEmpty() && empty($this->draft)) {
                        throw new \DomainException('No hay pedidos activos para cobrar en esta mesa.');
                    }

                    foreach ($table->orders as $order) {
                        $ordersToCharge->push($order);
                    }
                }

                // 2) Comanda en curso (draft) → crear el Order DENTRO de la
                //    transacción (re-validando cada ítem contra la DB).
                //    SOLO en modo 'nuevo': en 'existentes' el draft se ignora
                //    aunque venga en el payload (el mozo eligió otra cuenta).
                if ($this->cobroMode === 'nuevo' && ! empty($this->draft)) {
                    $draftOrder = $this->createOrderFromDraft();
                    $ordersToCharge->push($draftOrder);
                    $draftOrderIds[] = $draftOrder->id;
                }

                if ($ordersToCharge->isEmpty()) {
                    throw new \DomainException('No hay pedidos para cobrar.');
                }

                // Una venta por cada pedido (draft creado + comandas/pedido
                // seleccionado). El draft cobrado directo NO pasa a completed:
                // queda 'pending' para que la cocina lo vea y lo prepare.
                foreach ($ordersToCharge as $order) {
                    $this->chargeOrder(
                        $order,
                        $cajaAbierta,
                        $subtotal,
                        $discountAmount,
                        complete: ! in_array($order->id, $draftOrderIds, true),
                    );
                }

                // Liberar la mesa solo si se cobró una cuenta de MESA. El
                // cliente pagó (la cocina sigue preparando el draft cobrado en
                // paralelo): el draft queda 'pending', por lo que se desvincula
                // de la mesa (table_id → null) para que release() no lo cuente
                // como pedido activo que mantiene ocupada la mesa.
                if ($this->selectedTableId) {
                    if ($draftOrderIds !== []) {
                        Order::whereIn('id', $draftOrderIds)->update(['table_id' => null]);
                    }

                    // La instancia de $table se cargó ANTES de
                    // createOrderFromDraft(), que pudo ocupar la mesa
                    // (available/reserved → occupied) vía $order->table->occupy().
                    // Sin refresh() la instancia queda con el status STALE
                    // ('available') y release() devuelve false (exige
                    // status === occupied): la mesa quedaría 'occupied' en DB
                    // SIN comandas activas → mesa fantasma.
                    $table?->refresh();
                    $table?->release();
                }
            });
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Error al procesar el cobro')
                ->body($e->getMessage())
                ->persistent()
                ->send();

            return;
        }

        $isMesa = $this->selectedTableId !== null;

        Notification::make()
            ->success()
            ->title('Cuenta Cobrada')
            ->body($isMesa
                ? 'Se cobró la cuenta de la mesa correctamente.'
                : 'Se cobró el pedido correctamente.')
            ->send();

        // La página limpia/refresca su estado (CrearPedido → clearCart,
        // TableMap → loadTables).
        $this->dispatch('comanda-cobrada');

        $this->resetPanel();
    }

    /**
     * Crear el Order de la comanda en curso (draft) con los datos re-validados
     * contra la DB: existe, disponible, stock real suficiente y PRECIO REAL.
     * Corre solo DENTRO de la transacción de cobro.
     */
    private function createOrderFromDraft(): Order
    {
        if ($this->orderType === 'salon' && ! $this->selectedTableId) {
            throw new \DomainException('Seleccioná una mesa para cobrar la comanda de salón.');
        }

        $payload = [];

        foreach ($this->draft as $item) {
            $product = Product::where('restaurant_id', 1)->find($item['product_id'] ?? null);

            if (! $product || ! $product->is_available) {
                throw new \DomainException(($item['name'] ?? 'Un producto').' ya no está disponible.');
            }

            $qty = (int) ($item['qty'] ?? 0);

            if ($qty <= 0) {
                throw new \DomainException('La comanda en curso tiene cantidades inválidas.');
            }

            if ((int) $product->real_stock < $qty) {
                throw new \DomainException("Stock insuficiente para **{$product->name}** (disponible: {$product->real_stock}).");
            }

            // PRECIO REAL de la DB: nunca se confía en el precio del cliente.
            $payload[] = [
                'product_id' => $product->id,
                'quantity' => $qty,
                'price' => (float) $product->price,
                'notes' => trim((string) ($item['note'] ?? '')) !== '' ? trim((string) $item['note']) : null,
            ];
        }

        $data = [
            'restaurant_id' => 1,
            'waiter_id' => Auth::id(),
            // Queda 'pending': la cocina lo ve y lo prepara; el cobro NO lo
            // completa (chargeOrder recibe $complete = false para el draft).
            'status' => 'pending',
            'type' => $this->orderType,
            'stock_deducted' => false, // Se descuenta FEFO en el cobro de este mismo flujo.
            'notes' => trim($this->draftKitchenNote) !== '' ? trim($this->draftKitchenNote) : null,
        ];

        if ($this->orderType === 'salon') {
            $data['table_id'] = $this->selectedTableId;
        }

        $order = Order::create($data);
        $order->orderProducts()->createMany($payload);
        $order->load('orderProducts');

        // Máquina de estados: solo avanza available/reserved → occupied.
        // Si la mesa ya está ocupada, el pedido se suma y no se pisa nada.
        if ($this->orderType === 'salon') {
            $order->table?->occupy();
        }

        return $order;
    }

    /**
     * Cargar un pedido individual (crea la Sale + sale_payments, descuenta
     * stock FEFO si hace falta). Compartido por mesa/takeaway/draft: en la
     * mesa el descuento es proporcional entre comandas; en takeaway el
     * subtotal ES el total del pedido único (descuento directo).
     *
     * SPLIT: cada método del split recibe amount * (orderTotal/subtotal)
     * redondeado a 2; la última fila se ajusta para que la suma de
     * sale_payments == total_amount exacto (±0.01). Sin split → una fila con
     * el método único y el total.
     *
     * @param  bool  $complete  false → el pedido NO pasa a 'completed'
     *                          (draft cobrado directo: queda pending en cocina).
     */
    private function chargeOrder(Order $order, Caja $caja, float $subtotal, float $discountAmount, bool $complete = true): void
    {
        $order->loadMissing('orderProducts');

        // FIX stock: si el pedido nunca pasó por 'processing' (stock_deducted
        // = false), descontar stock FEFO AHORA, antes de marcar completed.
        // Idempotente.
        if (! $order->stock_deducted) {
            app(StockDeductionService::class)->deductForOrder($order);
        }

        // Total del pedido individual: Σ quantity * price (precios REALES).
        $orderTotal = $order->orderProducts->sum(fn ($item) => (float) $item->quantity * (float) $item->price);

        // Proporción de descuento para este pedido (patrón TableMap).
        // Con subtotal == orderTotal (takeaway) el descuento es directo.
        $proportionalDiscount = $subtotal > 0
            ? ($orderTotal / $subtotal) * $discountAmount
            : 0;

        $orderFinalTotal = max(0, $orderTotal - $proportionalDiscount);

        // SPLIT: desglose proporcional + método dominante de esta Sale.
        $allocations = $this->splitAllocations($orderFinalTotal, $orderTotal, $subtotal);
        $dominant = $this->dominantMethod($allocations);

        // Crear la venta (una por pedido; order_id es UNIQUE). La venta se
        // registra SIEMPRE al cobrar con éxito.
        $sale = Sale::create([
            'order_id' => $order->id,
            'restaurant_id' => 1,
            'caja_id' => $caja->id,
            'cashier_id' => Auth::id(),
            'total_amount' => $orderFinalTotal,
            'payment_method' => $dominant,
            'status' => 'paid',
        ]);

        // Desglose del pago (sale_payments): una fila por método con monto > 0.
        foreach ($allocations as $allocation) {
            if ($allocation['amount'] > 0.005) {
                $sale->payments()->create([
                    'payment_method' => $allocation['method'],
                    'amount' => $allocation['amount'],
                ]);
            }
        }

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

        // Las comandas ENVIADAS cobradas pasan a 'completed'; el draft cobrado
        // directo NO (queda pending para la cocina).
        if ($complete) {
            $order->update(['status' => 'completed']);
        }
    }

    /**
     * Reparto del SPLIT para una Sale: cada método recibe amount * (orderTotal
     * / subtotal) redondeado a 2; la ÚLTIMA fila se ajusta para que la suma de
     * sale_payments == total_amount exacto (±0.01). Con subtotal == orderTotal
     * (takeaway / pedido único) el reparto degenera en directo.
     *
     * @return array<int, array{method: string, amount: float}>
     */
    private function splitAllocations(float $orderFinalTotal, float $orderTotal, float $subtotal): array
    {
        $ratio = $subtotal > 0 ? $orderTotal / $subtotal : 0.0;

        $allocations = array_map(
            fn (array $row) => [
                'method' => $row['method'],
                'amount' => round($this->parseAmount($row['amount'] ?? '') * $ratio, 2),
            ],
            array_values($this->payments)
        );

        // Ajustar la última fila para que la suma coincida con el total de la Sale.
        if ($allocations !== []) {
            $lastIndex = count($allocations) - 1;
            $others = array_sum(array_map(
                fn (array $allocation) => $allocation['amount'],
                array_slice($allocations, 0, $lastIndex)
            ));
            $allocations[$lastIndex]['amount'] = round(max(0, $orderFinalTotal - $others), 2);
        }

        return $allocations;
    }

    /**
     * Método DOMINANTE de la Sale: el de mayor monto; desempate = el primero.
     */
    private function dominantMethod(array $allocations): string
    {
        if ($allocations === []) {
            return $this->paymentMethod;
        }

        $dominant = $allocations[0]['method'];
        $max = $allocations[0]['amount'];

        foreach ($allocations as $allocation) {
            if ($allocation['amount'] > $max) {
                $max = $allocation['amount'];
                $dominant = $allocation['method'];
            }
        }

        return $dominant;
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────

    /**
     * Draft de la página: el TPV (CrearPedido) expone $items; el mapa no.
     */
    private function pageDraft(): array
    {
        if (property_exists($this, 'items') && is_array($this->items)) {
            return $this->items;
        }

        return [];
    }

    private function pageKitchenNote(): string
    {
        return property_exists($this, 'kitchenNote') && is_string($this->kitchenNote)
            ? $this->kitchenNote
            : '';
    }

    private function pageOrderType(): string
    {
        return property_exists($this, 'orderType') && is_string($this->orderType)
            ? $this->orderType
            : 'salon';
    }

    /**
     * Total efectivo del split (solo filas cash).
     */
    private function cashRowsTotal(): float
    {
        return round(collect($this->payments)
            ->where('method', 'cash')
            ->sum(fn (array $row) => $this->parseAmount($row['amount'] ?? '')), 2);
    }

    /**
     * ¿Las filas del split son válidas? (≤ 3, métodos conocidos, montos > 0).
     */
    private function paymentsAreValid(array $payments): bool
    {
        if ($payments === [] || count($payments) > 3) {
            return false;
        }

        foreach ($payments as $row) {
            if (! in_array($row['method'] ?? '', ['cash', 'card', 'transfer'], true)) {
                return false;
            }

            if ($this->parseAmount($row['amount'] ?? '') <= 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Parsear un monto del input: acepta coma decimal ("12,50" → 12.50).
     */
    private function parseAmount(mixed $value): float
    {
        return (float) str_replace(',', '.', trim((string) $value));
    }

    private function validId(mixed $value): ?int
    {
        if ($value === null || $value === '' || (int) $value <= 0) {
            return null;
        }

        return (int) $value;
    }

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

    /**
     * Emoji de un ítem de la comanda en curso (draft) según su section_key.
     */
    public static function draftEmoji(string $sectionKey): string
    {
        return match ($sectionKey) {
            'bebidas' => '🥤',
            'papas' => '🍟',
            'hamburguesas' => '🍔',
            'entradas' => '🥗',
            'postres' => '🍰',
            default => '🍽️',
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

    private function methodLabel(string $method): string
    {
        return match ($method) {
            'cash' => 'Efectivo',
            'card' => 'Tarjeta',
            'transfer' => 'Transferencia',
            default => ucfirst($method),
        };
    }

    /**
     * Reset del estado del cobro conservando la cuenta seleccionada.
     */
    private function resetCobroState(): void
    {
        $this->paymentMethod = 'cash';
        $this->selectedDiscounts = [];
        $this->montoRecibido = '';

        // SPLIT: por defecto UNA fila [método = paymentMethod, monto = total].
        $this->payments = [[
            'method' => $this->paymentMethod,
            'amount' => $this->formatAmountInput($this->accountTotal()),
        ]];
    }

    /**
     * Reset completo del modal (post-cobro exitoso o cierre limpio).
     */
    private function resetPanel(): void
    {
        $this->open = false;
        $this->cobroMode = 'existentes';
        $this->selectedTableId = null;
        $this->selectedOrderId = null;
        $this->draft = [];
        $this->draftKitchenNote = '';
        $this->orderType = 'salon';
        $this->paymentMethod = 'cash';
        $this->selectedDiscounts = [];
        $this->montoRecibido = '';
        $this->payments = [];

        unset($this->account, $this->chargeableTables, $this->takeawayOrders);
    }

    private function formatAmountInput(float $value): string
    {
        return (string) round($value, 2);
    }
}