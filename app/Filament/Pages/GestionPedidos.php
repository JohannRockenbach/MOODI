<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasCobroRapido;
use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

/**
 * GestionPedidos — gestión de pedidos estilo Stitch ("Restaurante El Fogón",
 * mismo clima que el KDS Cocina pero orientado a la gestión del salón).
 *
 * Las acciones de estado repiten EXACTAMENTE las reglas de negocio de
 * OrderResource::table() (y del KDS): pending→processing dispara el
 * OrderObserver (descuenta stock automáticamente), processing→ready,
 * processing|ready→completed (NO desde pending, respetando la cadena visual
 * del KDS), pending|processing→cancelled (con aviso de merma si estaba en
 * processing: el stock NO se devuelve).
 *
 * El cobro NO se reimplementa: se reutiliza el trait HasCobroRapido (mismo
 * modal Stitch de TableMap/CrearPedido). Para pedidos de salón abre el cobro
 * por mesa; para llevar por pedido; delivery redirige a la edición (el modal
 * solo modela mesa/para llevar).
 */
class GestionPedidos extends Page
{
    use HasCobroRapido;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string $view = 'filament.pages.gestion-pedidos';

    /**
     * Gestión operativa a ancho completo, igual que TableMap/KDS.
     */
    protected ?string $maxContentWidth = MaxWidth::Full->value;

    protected static ?string $title = 'Pedidos';

    protected static ?string $navigationLabel = 'Pedidos';

    protected static ?string $navigationGroup = 'Operaciones del Salón';

    protected static ?int $navigationSort = 2;

    /**
     * Estados posibles de un pedido (fuente única, coincide con la app).
     */
    public const STATUS_LABELS = [
        'pending' => 'Pendiente',
        'processing' => 'En Preparación',
        'ready_for_pickup' => 'Listo',
        'completed' => 'Completado',
        'cancelled' => 'Cancelado',
    ];

    // Pedidos enriquecidos para el grid.
    public array $orders = [];

    // KPIs por estado.
    public array $stats = [
        'pending' => 0,
        'processing' => 0,
        'ready_for_pickup' => 0,
        'completed' => 0,
        'cancelled' => 0,
    ];

    // Buscador y filtro del header.
    public string $search = '';

    public string $activeStatus = 'all';

    // Modal de detalle del pedido (patrón Stitch inline, sin redirigir a edit).
    public ?int $pedidoModalId = null;

    /**
     * Acceso: super_admin y Mozo — el mismo conjunto que OrderPolicy::viewAny
     * (Cajero NO opera pedidos y Cocinero NO ve pedidos: solo cocina/recetas).
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->hasAnyRole(['super_admin', 'Mozo']);
    }

    /**
     * Items de navegación: solo "Pedidos" (esta página). El item "Cocina"
     * (KDS) lo registra RecetasCocina::getNavigationItems() con el mismo
     * conjunto super_admin/Cocinero, para que el Cocinero siga viéndolo
     * aunque ya no acceda a esta página (Filament v3 solo registra los items
     * de páginas cuyo canAccess() es true, y el resource OrderResource tiene
     * shouldRegisterNavigation = false).
     */
    public static function getNavigationItems(): array
    {
        return parent::getNavigationItems();
    }

    public function mount(): void
    {
        $this->loadOrders();
    }

    /**
     * Cargar los pedidos enriquecidos (auto-refresh vía wire:poll del blade).
     * Filtra por restaurante según el rol (super_admin ve todo; el resto su
     * restaurante), mismo criterio que OrderResource::getEloquentQuery.
     */
    public function loadOrders(): void
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        $isSuperAdmin = $user && $user->hasRole('super_admin');

        $query = Order::with([
            'table',
            'waiter',
            'orderProducts.product.category',
        ])->withExists('sale') // evita N+1 para ocultar "Cobrar" si ya tiene venta
            ->where('restaurant_id', 1);

        if (! $isSuperAdmin && $user?->restaurant_id) {
            $query->where('restaurant_id', $user->restaurant_id);
        }

        $orders = $query
            ->orderBy('created_at', 'desc')
            ->get();

        $this->orders = $orders
            ->map(fn (Order $order): array => [
                'id' => $order->id,
                'status' => $order->status,
                'status_label' => self::STATUS_LABELS[$order->status] ?? ucfirst($order->status),
                'type' => $order->type,
                'table_number' => $order->table?->number,
                'customer_name' => $order->customer_name,
                'waiter_name' => $order->waiter?->name ?? 'Pedido Web',
                'notes' => (string) $order->notes,
                'has_sale' => (bool) $order->sale_exists,
                'created_at' => $order->created_at,
                'total' => round($order->orderProducts->sum(fn ($item) => (float) $item->quantity * (float) $item->price), 2),
                'items' => $order->orderProducts
                    ->filter(fn ($item) => ($item->product?->category?->name ?? '') !== 'Bebidas')
                    ->map(fn ($item) => [
                        'name' => $item->product?->name ?? 'Producto eliminado',
                        'quantity' => (int) $item->quantity,
                        'notes' => trim((string) $item->notes),
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        $this->stats = [
            'pending' => $orders->where('status', 'pending')->count(),
            'processing' => $orders->where('status', 'processing')->count(),
            'ready_for_pickup' => $orders->where('status', 'ready_for_pickup')->count(),
            'completed' => $orders->where('status', 'completed')->count(),
            'cancelled' => $orders->where('status', 'cancelled')->count(),
        ];

        unset($this->visibleOrders);
    }

    /**
     * Pedidos visibles según estado activo y búsqueda (id, cliente o mesa).
     * El blade itera SOLO esta colección (morph estable de Livewire).
     */
    #[Computed]
    public function visibleOrders(): array
    {
        return collect($this->orders)
            ->filter(fn (array $order): bool => $this->activeStatus === 'all' || $order['status'] === $this->activeStatus)
            ->filter(fn (array $order): bool => $this->orderMatchesSearch($order))
            ->values()
            ->all();
    }

    /**
     * ¿El pedido coincide con el buscador? Filtra por id, cliente y mesa.
     */
    public function orderMatchesSearch(array $order): bool
    {
        if ($this->search === '') {
            return true;
        }

        $query = mb_strtolower(trim($this->search));

        $haystack = mb_strtolower(implode(' ', [
            (string) $order['id'],
            (string) ($order['customer_name'] ?? ''),
            (string) ($order['table_number'] ?? ''),
            (string) ($order['waiter_name'] ?? ''),
        ]));

        return str_contains($haystack, $query);
    }

    /**
     * ¿El usuario actual es super_admin? (badges/KPI condicionales).
     */
    public function isSuperAdmin(): bool
    {
        return (bool) (auth()->user()?->hasRole('super_admin'));
    }

    /**
     * ¿Puede cobrar? El modal de cobro (HasCobroRapido::cobrar) exige
     * super_admin/Cajero/Mozo; la página admite también a Cocinero, así que el
     * botón solo se muestra a quien puede cobrar (defensa en profundidad: el
     * trait igual aborta 403 en cobrar()).
     */
    public function puedeCobrar(): bool
    {
        return (bool) (auth()->user()?->hasAnyRole(['super_admin', 'Mozo']));
    }

    /**
     * Marcar En Proceso: pending → processing. AL PASAR A PROCESSING SE
     * DISPARA EL OrderObserver → descuenta stock automáticamente (NO se hace
     * manual). Visible solo si el pedido sigue pendiente.
     */
    public function startProcessing(int $orderId): void
    {
        $order = Order::find($orderId);

        if (! $order || $order->status !== 'pending') {
            return;
        }

        $order->update(['status' => 'processing']);

        Notification::make()
            ->title('Pedido En Proceso')
            ->body("El pedido #{$orderId} pasó a En Preparación. El stock se descontó automáticamente.")
            ->success()
            ->send();

        $this->loadOrders();
        $this->dispatch('orderStatusChanged');
    }

    /**
     * Marcar Listo: processing → ready_for_pickup.
     */
    public function markAsReady(int $orderId): void
    {
        $order = Order::find($orderId);

        if (! $order || $order->status !== 'processing') {
            return;
        }

        $order->update(['status' => 'ready_for_pickup']);

        Notification::make()
            ->title('Pedido Listo')
            ->body("El pedido #{$orderId} está listo para retirar.")
            ->success()
            ->send();

        $this->loadOrders();
        $this->dispatch('orderStatusChanged');
    }

    /**
     * Marcar Completado: processing|ready_for_pickup → completed.
     * NO se permite desde pending (a diferencia del resource): acá se respeta
     * la cadena visual del KDS (un pedido pendiente todavía no se preparó).
     */
    public function markAsCompleted(int $orderId): void
    {
        $order = Order::find($orderId);

        if (! $order || ! in_array($order->status, ['processing', 'ready_for_pickup'], true)) {
            return;
        }

        $order->update(['status' => 'completed']);

        Notification::make()
            ->title('Pedido Completado')
            ->body("El pedido #{$orderId} fue completado. La mesa se liberó automáticamente si no quedaban pedidos activos.")
            ->success()
            ->send();

        $this->loadOrders();
        $this->dispatch('orderStatusChanged');
    }

    /**
     * Cancelar: pending|processing → cancelled. Si estaba en processing el
     * stock ya fue descontado (merma, NO se devuelve) — aviso diferenciado
     * igual que OrderResource.
     */
    public function cancelOrder(int $orderId): void
    {
        $order = Order::find($orderId);

        if (! $order || ! in_array($order->status, ['pending', 'processing'], true)) {
            return;
        }

        $previousStatus = $order->status;
        $order->update(['status' => 'cancelled']);

        if ($previousStatus === 'pending') {
            Notification::make()
                ->title('Pedido Cancelado')
                ->body("El pedido #{$orderId} ha sido cancelado correctamente. No se usaron ingredientes.")
                ->success()
                ->send();
        } else {
            Notification::make()
                ->warning()
                ->title('Pedido Cancelado')
                ->body("El pedido #{$orderId} fue cancelado. Los ingredientes ya usados se registran como merma.")
                ->icon('heroicon-o-exclamation-triangle')
                ->send();
        }

        $this->loadOrders();
        $this->dispatch('orderStatusChanged');
    }

    /**
     * Ver pedido: abre el modal Stitch con TODOS los productos del pedido
     * (incluye bebidas, a diferencia del resumen de la tarjeta), cada uno con
     * cantidad, precio unitario, subtotal y notas; más el total. NO navega a
     * la página de edición de Filament.
     */
    public function verPedido(int $orderId): void
    {
        $this->pedidoModalId = $orderId;
    }

    /**
     * Datos completos del pedido para el modal de detalle. Incluye bebidas y
     * todos los ítems (el listado las oculta en el resumen; el detalle las
     * muestra porque el cobro/venta usa todo).
     */
    #[Computed]
    public function detallePedido(): ?array
    {
        if (! $this->pedidoModalId) {
            return null;
        }

        $order = Order::with(['table', 'waiter', 'orderProducts.product.category'])
            ->withExists('sale')
            ->where('restaurant_id', 1)
            ->find($this->pedidoModalId);

        if (! $order) {
            return null;
        }

        return [
            'id' => $order->id,
            'status' => $order->status,
            'status_label' => self::STATUS_LABELS[$order->status] ?? ucfirst($order->status),
            'type' => $order->type,
            'table_number' => $order->table?->number,
            'customer_name' => $order->customer_name,
            'waiter_name' => $order->waiter?->name ?? 'Pedido Web',
            'notes' => (string) $order->notes,
            'has_sale' => (bool) $order->sale_exists,
            'created_at' => $order->created_at,
            'total' => round($order->orderProducts->sum(fn ($item) => (float) $item->quantity * (float) $item->price), 2),
            'items' => $order->orderProducts
                ->map(fn ($item) => [
                    'name' => $item->product?->name ?? 'Producto eliminado',
                    'category' => $item->product?->category?->name ?? '',
                    'quantity' => (int) $item->quantity,
                    'price' => (float) $item->price,
                    'subtotal' => round((float) $item->quantity * (float) $item->price, 2),
                    'notes' => trim((string) $item->notes),
                ])
                ->values()
                ->all(),
        ];
    }

    public function cerrarPedidoModal(): void
    {
        $this->pedidoModalId = null;
    }

    /**
     * Cobrar pedido reutilizando el modal Stitch (HasCobroRapido):
     * - salón → abre el cobro de la MESA (todas sus comandas abiertas).
     * - para_llevar → abre el cobro del PEDIDO.
     * - delivery → el modal solo modela mesa/para llevar; redirige a la
     *   edición donde el recurso registra la venta.
     * Solo visible sin venta registrada y para quien puede cobrar.
     */
    public function cobrarPedido(int $orderId): void
    {
        if (! $this->puedeCobrar()) {
            abort(403);
        }

        $order = Order::with('table')->find($orderId);

        if (! $order || $order->restaurant_id !== 1 || $order->sale()->exists()) {
            return;
        }

        if ($order->type === 'salon' && $order->table_id) {
            $this->abrirCobro(tableId: $order->table_id);
        } elseif ($order->type === 'para_llevar') {
            $this->abrirCobro(orderId: $order->id);
        } else {
            // delivery: el cobro se gestiona desde el form del resource.
            $this->redirect(
                OrderResource::getUrl('edit', ['record' => $order->id])
            );
        }
    }

    /**
     * El cobro terminó (la página limpia/refresca su estado tras el evento).
     */
    #[\Livewire\Attributes\On('comanda-cobrada')]
    public function onComandaCobrada(): void
    {
        $this->loadOrders();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refrescar')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn () => $this->loadOrders())
                ->keyBindings(['f5']),
        ];
    }
}