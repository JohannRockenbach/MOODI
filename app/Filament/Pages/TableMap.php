<?php

namespace App\Filament\Pages;

use App\Models\Reservation;
use App\Models\Table;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

class TableMap extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static string $view = 'filament.pages.table-map';

    protected static ?string $title = 'Mapa de Mesas';

    protected static ?string $navigationLabel = 'Mapa de Mesas';

    protected static ?string $navigationGroup = 'Operaciones del Salón';

    protected static ?int $navigationSort = 1;

    /**
     * Claves normalizadas de zona y su etiqueta de display.
     * Las mesas pueden tener location legacy en español ("Terraza",
     * "Salon"/"Salón", "Barra"); toda la app agrupa por estas claves.
     */
    public const ZONE_LABELS = [
        'terraza' => 'Terraza',
        'salon' => 'Salón',
        'barra' => 'Barra',
    ];

    // Propiedades principales
    public array $tablesByLocation = [];

    public array $stats = [
        'available' => 0,
        'occupied' => 0,
        'reserved' => 0,
        'por_cobrar' => 0,
        'por_cobrar_total' => 0,
        'avg_stay' => null,
        'next_turn' => null,
    ];

    public ?int $selectedTableId = null;

    // Buscador y filtro de zona (estado del header)
    public string $search = '';

    public string $activeZone = 'all';

    // Propiedades para acciones de mesa a distancia (Cambiar Mesa / Unir Mesas)
    public ?string $tableAction = null; // 'move' | 'merge' | null

    public ?int $targetTableId = null;

    // Propiedades para el modal "Nueva Mesa"
    public string $newNumber = '';

    public string $newLocation = 'terraza';

    public $newCapacity = 4;

    // Propiedades para cobro de mesa
    public string $paymentMethod = 'cash';

    public array $selectedDiscounts = [];

    public float $totalAmount = 0;

    public float $discountAmount = 0;

    // Visibilidad según rol
    public static function canAccess(): bool
    {
        // Mapa de mesas es operativo: super_admin, Mozo y Cajero.
        $user = auth()->user();

        return $user !== null && $user->hasAnyRole(['super_admin', 'Mozo', 'Cajero']);
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Visible para todos los usuarios autenticados en el panel admin
        // El acceso ya está protegido por canAccess() y el middleware de Filament
        return true;
    }

    // Montar el componente
    public function mount(): void
    {
        $this->loadTables();
    }

    /**
     * Cargar las mesas agrupadas por zona normalizada (terraza/salon/barra),
     * enriquecidas con datos operativos reales para el mapa y el panel lateral.
     */
    #[On('refresh-map')]
    public function loadTables(): void
    {
        // Obtener todas las mesas con pedidos activos, reservas futuras y mozo.
        $tables = Table::where('restaurant_id', 1)
            ->with([
                'orders' => fn ($query) => $query
                    ->whereIn('status', Table::ORDER_OPEN_STATUSES)
                    ->with(['orderProducts.product', 'waiter'])
                    ->orderBy('created_at', 'desc'),
                'reservations' => fn ($query) => $query
                    ->whereIn('status', Table::RESERVATION_ACTIVE_STATUSES)
                    ->where('reservation_time', '>', now())
                    ->with('customer')
                    ->orderBy('reservation_time', 'asc'),
                'waiter',
            ])
            ->orderBy('number')
            ->get();

        // Agrupar por zona normalizada, manteniendo el orden del plano (Terraza, Salón, Barra).
        $zones = array_fill_keys(array_keys(self::ZONE_LABELS), []);

        foreach ($tables as $table) {
            $zone = $this->normalizeZone($table->location);
            $activeOrders = $table->orders;
            $firstOrder = $activeOrders->first();
            $nextReservation = $table->reservations->first();

            $zones[$zone][] = [
                'id' => $table->id,
                'number' => $table->number,
                'location' => $table->location,
                'zone' => $zone,
                'zone_label' => self::ZONE_LABELS[$zone],
                'capacity' => $table->capacity,
                'status' => $table->status,
                'orders_count' => $activeOrders->count(),
                'total_amount' => $this->tablesTotal($activeOrders),
                'elapsed_time' => $firstOrder?->created_at->diffForHumans(null, true),
                'first_order_id' => $firstOrder?->id,
                'has_reservation' => $nextReservation !== null,
                'reservation_info' => $nextReservation ? $this->reservationInfo($nextReservation) : null,
                'waiter_name' => $table->waiter?->name ?? $firstOrder?->waiter?->name ?? 'Sin asignar',
            ];
        }

        $this->tablesByLocation = $zones;

        // ── KPIs reales ────────────────────────────────────────────────
        $occupied = $tables->where('status', Table::STATUS_OCCUPIED);
        // POR COBRAR: mesas ocupadas con pedidos activos (cuenta + monto pendiente).
        $occupiedWithOrders = $occupied->filter(fn (Table $t) => $t->orders->isNotEmpty());

        // Promedio de permanencia: sobre mesas ocupadas con permanencia conocida.
        $stayMinutes = $occupiedWithOrders
            ->map(fn (Table $t) => $t->orders->first()->created_at->diffInMinutes(now()))
            ->filter(fn (int $min) => $min >= 0);

        // Próximo turno: la reserva futura más cercana entre mesas reservadas.
        $nextTurnAt = $tables
            ->where('status', Table::STATUS_RESERVED)
            ->filter(fn (Table $t) => $t->reservations->isNotEmpty())
            ->map(fn (Table $t) => $t->reservations->first()->reservation_time)
            ->min();

        $this->stats = [
            'available' => $tables->where('status', Table::STATUS_AVAILABLE)->count(),
            'occupied' => $occupied->count(),
            'reserved' => $tables->where('status', Table::STATUS_RESERVED)->count(),
            'por_cobrar' => $occupiedWithOrders->count(),
            'por_cobrar_total' => round($occupiedWithOrders->sum(fn (Table $t) => $this->tablesTotal($t->orders)), 2),
            'avg_stay' => $stayMinutes->isNotEmpty() ? (int) round($stayMinutes->avg()) : null,
            'next_turn' => $nextTurnAt?->format('H:i'),
        ];

        // Invalidar computed que dependen de tablesByLocation para evitar servir
        // datos viejos si se re-ejecuta loadTables en el mismo ciclo de request.
        unset($this->visibleZones, $this->targetTableOptions);
    }

    /**
     * Zonas visibles según el filtro activo (Todas / Terraza / Salón / Barra).
     * Fuente única de verdad para el plano: el blade itera esto SIN @continue,
     * así el morph de Livewire nunca deja un bloque de zona huérfano (wire:key).
     */
    #[Computed]
    public function visibleZones(): array
    {
        $zones = $this->tablesByLocation;

        if ($this->activeZone !== 'all') {
            $zones = array_intersect_key($zones, [$this->activeZone => true]);
        }

        // Omitir zonas sin mesas (mismo comportamiento del @forelse original).
        return array_filter($zones, fn (array $tables) => $tables !== []);
    }

    /**
     * Seleccionar una mesa → muestra sus especificaciones en el panel derecho.
     * Reemplaza el antiguo openTable() que abría un modal de detalle.
     */
    public function selectTable(int $tableId): void
    {
        $this->authorizeStaffAccess();

        $table = Table::find($tableId);

        if (! $table || $table->restaurant_id !== 1) {
            return;
        }

        $this->selectedTableId = $tableId;
        $this->loadTables();

        $this->dispatch('table-selected', message: "Mesa {$table->number} seleccionada para gestión");
    }

    // Verificación de acceso para acciones operativas del mapa (staff).
    // La página ya exige rol vía canAccess(); esto protege las acciones por método.
    private function authorizeStaffAccess(): void
    {
        abort_unless(
            auth()->user()?->hasAnyRole(['super_admin', 'Mozo', 'Cajero']),
            403
        );
    }

    // Crear nuevo pedido (SIN cambiar estado aquí)
    public function createOrderForTable(): void
    {
        $this->authorizeStaffAccess();

        if (! $this->selectedTableId) {
            return;
        }

        // NO cambiar el estado aquí - se cambiará cuando se GUARDE el pedido
        $this->redirect(
            \App\Filament\Resources\OrderResource::getUrl('create', ['table_id' => $this->selectedTableId])
        );
    }

    // Ir a editar un pedido existente
    public function editOrder(int $orderId): void
    {
        $this->authorizeStaffAccess();

        $this->redirect(
            \App\Filament\Resources\OrderResource::getUrl('edit', [
                'record' => $orderId,
                'from_map' => 1, // 🔒 Indicar que viene del mapa para bloquear campos
            ])
        );
    }

    // Ir a crear una reserva para la mesa seleccionada (patrón de createOrderForTable:
    // el query param table_id prellena el form en CreateReservation).
    public function createReservation(): void
    {
        $this->authorizeStaffAccess();

        if (! $this->selectedTableId) {
            return;
        }

        $this->redirect(
            \App\Filament\Resources\ReservationResource::getUrl('create', ['table_id' => $this->selectedTableId])
        );
    }

    // Liberar una mesa
    public function freeTable(int $tableId): void
    {
        $this->authorizeStaffAccess();

        $table = Table::with('orders')->find($tableId);

        if (! $table) {
            return;
        }

        // Verificar que no haya pedidos activos
        $hasActiveOrders = $table->orders()
            ->whereIn('status', Table::ORDER_OPEN_STATUSES)
            ->exists();

        if ($hasActiveOrders) {
            Notification::make()
                ->title('No se puede liberar')
                ->body('La mesa tiene pedidos activos. Completa o cancela los pedidos primero.')
                ->warning()
                ->send();

            return;
        }

        // Liberar la mesa con la máquina de estados centralizada.
        // - 'occupied': release() la pasa a 'available' (o 'reserved' si hay reservas
        //   futuras vigentes), validando que no queden pedidos activos.
        // - 'reserved': se libera manualmente solo si no hay reservas futuras vigentes;
        //   si las hay, se mantiene reservada (un admin debe gestionar la reserva).
        if ($table->status === Table::STATUS_OCCUPIED) {
            $table->release();
        } elseif ($table->status === Table::STATUS_RESERVED && ! $table->hasFutureActiveReservations()) {
            $table->update(['status' => Table::STATUS_AVAILABLE]);
        }
        // 'available' / 'maintenance': no se tocan (maintenance fuera de servicio).

        Notification::make()
            ->title('Mesa Liberada')
            ->body("Mesa #{$table->number} ahora está disponible")
            ->success()
            ->send();

        $this->loadTables();
    }

    /**
     * Mesas candidatas según la acción de mesa abierta en el panel:
     * - 'move': mesas DISPONIBLES (destino posible para "Cambiar Mesa").
     * - 'merge': mesas OCUPADAS con pedidos activos (origen a unir a la seleccionada).
     * Siempre excluye la mesa seleccionada y filtra por restaurante 1.
     */
    #[Computed]
    public function targetTableOptions(): array
    {
        if (! $this->tableAction || ! $this->selectedTableId) {
            return [];
        }

        $tables = Table::where('restaurant_id', 1)
            ->where('id', '!=', $this->selectedTableId)
            ->withCount(['orders as active_orders_count' => fn ($query) => $query->whereIn('status', Table::ORDER_OPEN_STATUSES)])
            ->orderBy('number')
            ->get();

        if ($this->tableAction === 'move') {
            $tables = $tables->where('status', Table::STATUS_AVAILABLE);
        } elseif ($this->tableAction === 'merge') {
            // Ocupadas con pedidos activos: filtramos en PHP (evita group-by
            // de Postgres al usar having sobre el alias de withCount).
            $tables = $tables
                ->where('status', Table::STATUS_OCCUPIED)
                ->filter(fn (Table $t) => $t->active_orders_count > 0);
        }

        return array_values($tables->map(fn (Table $t) => [
            'id' => $t->id,
            'number' => $t->number,
            'zone' => $this->normalizeZone($t->location),
            'capacity' => $t->capacity,
            'active_orders_count' => (int) $t->active_orders_count,
        ])->all());
    }

    /**
     * Abrir el modal de acción de mesa ("move" = Cambiar Mesa, "merge" = Unir Mesas)
     * con su lista de mesas candidatas. Solo staff.
     */
    public function openTableAction(string $action): void
    {
        $this->authorizeStaffAccess();

        if (! $this->selectedTableId || ! in_array($action, ['move', 'merge'], true)) {
            return;
        }

        $this->tableAction = $action;
        $this->targetTableId = null;
        unset($this->targetTableOptions);
    }

    /**
     * Cerrar el modal de acción de mesa y limpiar selección.
     */
    public function closeTableAction(): void
    {
        $this->tableAction = null;
        $this->targetTableId = null;
    }

    /**
     * CAMBIAR MESA: mover los pedidos ACTIVOS de la mesa seleccionada (origen)
     * a una mesa destino DISPONIBLE, ocupar el destino con la máquina de estados
     * (occupy: available → occupied) y liberar el origen con release() — que
     * respeta reservas futuras (pasa a 'reserved' si hay una vigente).
     * Transaccional con row-locks para evitar carreras entre mozos.
     */
    public function moveOrdersToTable(): void
    {
        $this->authorizeStaffAccess();

        $origen = Table::find($this->selectedTableId);
        $destino = Table::find($this->targetTableId);

        // Validaciones: origen ocupada con pedidos activos; destino del restaurante
        // y disponible (no ocupada ni en mantenimiento).
        if (! $origen || $origen->restaurant_id !== 1 || $origen->status !== Table::STATUS_OCCUPIED || ! $origen->hasActiveOrders()) {
            Notification::make()
                ->title('No se puede cambiar la mesa')
                ->body('La mesa seleccionada no está ocupada con pedidos activos.')
                ->warning()
                ->send();

            return;
        }

        if (! $destino || $destino->restaurant_id !== 1 || $destino->status !== Table::STATUS_AVAILABLE) {
            Notification::make()
                ->title('Mesa destino inválida')
                ->body('La mesa destino no existe o no está disponible (ocupada o en mantenimiento).')
                ->warning()
                ->send();

            return;
        }

        if ($origen->id === $destino->id) {
            return;
        }

        DB::transaction(function () use ($origen, $destino) {
            // Row-locks: la validación de arriba es pre-check; el lock re-valida
            // en la misma transacción que la escritura.
            $origenLocked = Table::whereKey($origen->id)->lockForUpdate()->first();
            $destinoLocked = Table::whereKey($destino->id)->lockForUpdate()->first();

            $origenLocked->orders()
                ->whereIn('status', Table::ORDER_OPEN_STATUSES)
                ->update(['table_id' => $destinoLocked->id]);

            // Destino disponible → occupy() avanza la máquina de estados sin saltos
            // (available → occupied). Nunca pisa una mesa ocupada ni maintenance.
            $destinoLocked->occupy();

            // Origen sin pedidos activos → release() lo libera ('available'), o lo
            // deja 'reserved' si hay una reserva futura vigente.
            $origenLocked->release();
        });

        Notification::make()
            ->title('Mesa Cambiada')
            ->body("Los pedidos de la mesa #{$origen->number} se movieron a la mesa #{$destino->number}")
            ->success()
            ->send();

        $this->closeTableAction();
        $this->loadTables();
    }

    /**
     * UNIR MESAS: mover TODOS los pedidos activos de la mesa elegida (origen)
     * a la mesa SELECCIONADA (destino, que queda sumando), y liberar la mesa
     * origen con release().
     *
     * Capacidad: Order no tiene dato de comensales (guest_count) en el dominio,
     * así que no se puede verificar "capacidad suficiente" con datos reales; por
     * eso NO bloqueamos por capacidad y avisamos en la notificación que no se
     * pudo calcular los comensales totales.
     */
    public function mergeOrdersIntoTable(): void
    {
        $this->authorizeStaffAccess();

        $destino = Table::find($this->selectedTableId); // mesa que queda (seleccionada)
        $origen = Table::find($this->targetTableId);    // mesa que se une (se libera)

        if (! $destino || $destino->restaurant_id !== 1 || $destino->status !== Table::STATUS_OCCUPIED || ! $destino->hasActiveOrders()) {
            Notification::make()
                ->title('No se puede unir')
                ->body('La mesa seleccionada no está ocupada con pedidos activos.')
                ->warning()
                ->send();

            return;
        }

        if (! $origen || $origen->restaurant_id !== 1 || $origen->id === $destino->id || $origen->status !== Table::STATUS_OCCUPIED || ! $origen->hasActiveOrders()) {
            Notification::make()
                ->title('Mesa a unir inválida')
                ->body('La mesa elegida no existe, es la misma mesa, o no tiene pedidos activos.')
                ->warning()
                ->send();

            return;
        }

        $moved = 0;

        DB::transaction(function () use ($destino, $origen, &$moved) {
            $destinoLocked = Table::whereKey($destino->id)->lockForUpdate()->first();
            $origenLocked = Table::whereKey($origen->id)->lockForUpdate()->first();

            $moved = $origenLocked->orders()
                ->whereIn('status', Table::ORDER_OPEN_STATUSES)
                ->update(['table_id' => $destinoLocked->id]);

            // Origen sin pedidos activos → release() (respeta reservas futuras).
            $origenLocked->release();
        });

        Notification::make()
            ->title('Mesas Unidas')
            ->body("Se movieron {$moved} pedidos de la mesa #{$origen->number} a la mesa #{$destino->number}. Verificá que la mesa quede con capacidad suficiente (no se pudo calcular comensales).")
            ->success()
            ->send();

        $this->closeTableAction();
        $this->loadTables();
    }

    /**
     * Cambiar estado de la mesa (toggle simple disponible ↔ mantenimiento).
     * Solo super_admin. No aplica si la mesa está ocupada/reservada o tiene
     * pedidos activos.
     */
    public function toggleTableStatus(int $tableId): void
    {
        if (! auth()->user()?->hasRole('super_admin')) {
            Notification::make()
                ->title('Permiso denegado')
                ->body('Solo el super_admin puede cambiar el estado de una mesa.')
                ->warning()
                ->send();

            return;
        }

        $table = Table::find($tableId);

        if (! $table || $table->restaurant_id !== 1) {
            return;
        }

        if ($table->hasActiveOrders() || in_array($table->status, [Table::STATUS_OCCUPIED, Table::STATUS_RESERVED], true)) {
            Notification::make()
                ->title('No se puede cambiar el estado')
                ->body('La mesa está ocupada, reservada o tiene pedidos activos.')
                ->warning()
                ->send();

            return;
        }

        $next = $table->status === Table::STATUS_MAINTENANCE
            ? Table::STATUS_AVAILABLE
            : Table::STATUS_MAINTENANCE;

        $table->update(['status' => $next]);

        Notification::make()
            ->title('Estado actualizado')
            ->body('Mesa #'.$table->number.' ahora está '.($next === Table::STATUS_MAINTENANCE ? 'en mantenimiento' : 'disponible'))
            ->success()
            ->send();

        $this->loadTables();
    }

    /**
     * Crear una mesa nueva desde el modal "Añadir Nueva Mesa".
     * Zona normalizada se persiste con el label de display (formato legacy español).
     */
    public function createTable(): void
    {
        $this->authorizeStaffAccess();

        $validated = $this->validate([
            'newNumber' => ['required', 'integer', 'min:1', Rule::unique('tables', 'number')->where(fn ($query) => $query->where('restaurant_id', 1))],
            'newLocation' => ['required', Rule::in(array_keys(self::ZONE_LABELS))],
            'newCapacity' => ['required', 'integer', 'between:1,20'],
        ]);

        $table = Table::create([
            'number' => $validated['newNumber'],
            'location' => self::ZONE_LABELS[$validated['newLocation']],
            'capacity' => $validated['newCapacity'],
            'status' => Table::STATUS_AVAILABLE,
            'restaurant_id' => 1,
            // Defensa en profundidad: el default de DB (0) evita el NOT NULL,
            // pero persistimos las coordenadas explícitamente para que el insert
            // nunca dependa del estado del schema (floor plan legacy espera ints).
            'pos_x' => 0,
            'pos_y' => 0,
        ]);

        $this->reset('newNumber', 'newCapacity');
        $this->newLocation = 'terraza';

        $this->dispatch('table-selected', message: "Mesa {$table->number} ({$table->capacity} pax) dada de alta correctamente");
        $this->dispatch('new-table-created');

        $this->loadTables();
    }

    // Preparar datos para cobrar la mesa
    public function prepareCobroMesa(int $tableId): void
    {
        $table = Table::with(['orders' => function ($query) {
            $query->whereIn('status', Table::ORDER_OPEN_STATUSES)
                ->with('orderProducts');
        }])->find($tableId);

        if (! $table || $table->orders->isEmpty()) {
            Notification::make()
                ->title('Error')
                ->body('No hay pedidos activos para cobrar en esta mesa')
                ->danger()
                ->send();

            return;
        }

        // Calcular total de todos los pedidos
        $total = $this->tablesTotal($table->orders);

        $this->selectedTableId = $tableId;
        $this->totalAmount = $total;
        $this->discountAmount = 0;
        $this->selectedDiscounts = [];
        $this->paymentMethod = 'cash';
    }

    // Calcular descuentos seleccionados
    public function updatedSelectedDiscounts(): void
    {
        if (empty($this->selectedDiscounts)) {
            $this->discountAmount = 0;

            return;
        }

        $discounts = \App\Models\Discount::whereIn('id', $this->selectedDiscounts)
            ->where('is_active', true)
            ->get();
        $totalDiscount = 0;

        foreach ($discounts as $discount) {
            if ($discount->type === 'percentage') {
                $totalDiscount += $this->totalAmount * ($discount->value / 100);
            } else {
                $totalDiscount += $discount->value;
            }
        }

        $this->discountAmount = $totalDiscount;
    }

    // Procesar el cobro de la mesa
    public function cobrarMesa(): void
    {
        // Solo Cajero/super_admin pueden registrar ventas (SalePolicy::create).
        abort_unless(auth()->user()?->can('create', \App\Models\Sale::class), 403);

        if (! $this->selectedTableId) {
            return;
        }

        $table = Table::with(['orders' => function ($query) {
            $query->whereIn('status', Table::ORDER_OPEN_STATUSES)
                ->with('orderProducts');
        }])->find($this->selectedTableId);

        if (! $table || $table->orders->isEmpty()) {
            Notification::make()
                ->title('Error')
                ->body('No hay pedidos activos para cobrar')
                ->danger()
                ->send();

            return;
        }

        // Verificar que haya una caja abierta
        $cajaAbierta = \App\Models\Caja::where('restaurant_id', 1)
            ->where('status', 'abierta')
            ->first();

        if (! $cajaAbierta) {
            Notification::make()
                ->title('Error')
                ->body('No hay una caja abierta. Abre una caja antes de registrar ventas.')
                ->danger()
                ->send();

            return;
        }

        try {
            DB::transaction(function () use ($table, $cajaAbierta) {
                $finalTotal = max(0, $this->totalAmount - $this->discountAmount);

                // Crear una venta por cada pedido de la mesa
                foreach ($table->orders as $order) {
                    // Calcular total del pedido individual
                    $orderTotal = $order->orderProducts->sum(function ($item) {
                        return $item->quantity * $item->price;
                    });

                    // Calcular proporción de descuento para este pedido
                    $proportionalDiscount = $this->totalAmount > 0
                        ? ($orderTotal / $this->totalAmount) * $this->discountAmount
                        : 0;

                    $orderFinalTotal = max(0, $orderTotal - $proportionalDiscount);

                    // Crear la venta
                    $sale = \App\Models\Sale::create([
                        'order_id' => $order->id,
                        'restaurant_id' => 1,
                        'caja_id' => $cajaAbierta->id,
                        'cashier_id' => Auth::id(),
                        'total_amount' => $orderFinalTotal,
                        'payment_method' => $this->paymentMethod,
                        'status' => 'paid',
                        'sale_date' => now(),
                    ]);

                    // Asociar descuentos proporcionalmente (solo si siguen activos)
                    if (! empty($this->selectedDiscounts)) {
                        foreach ($this->selectedDiscounts as $discountId) {
                            $discount = \App\Models\Discount::where('id', $discountId)
                                ->where('is_active', true)
                                ->first();
                            if ($discount) {
                                $discountValue = $discount->type === 'percentage'
                                    ? $orderTotal * ($discount->value / 100)
                                    : ($orderTotal / $this->totalAmount) * $discount->value;

                                $sale->discounts()->attach($discountId, [
                                    'amount_discounted' => $discountValue,
                                ]);
                            }
                        }
                    }

                    // El pedido cobrado deja de estar activo: pasa a 'completed'.
                    $order->update(['status' => 'completed']);
                }

                // Liberar la mesa con la máquina de estados centralizada.
                $table->release();
            });

            Notification::make()
                ->title('Mesa Cobrada')
                ->body("Se registró el cobro de la mesa #{$table->number} correctamente")
                ->success()
                ->send();

            // Refrescar y cerrar modales
            $this->loadTables();
            $this->dispatch('close-modal', id: 'cobrar-mesa');

            // Limpiar variables
            $this->selectedTableId = null;
            $this->selectedDiscounts = [];
            $this->paymentMethod = 'cash';
            $this->totalAmount = 0;
            $this->discountAmount = 0;

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error al procesar el cobro')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    // Propiedad computada: la mesa seleccionada con todas sus especificaciones
    // para el panel derecho (detalle, comanda en curso y total a pagar).
    #[Computed]
    public function selectedTable(): ?array
    {
        if (! $this->selectedTableId) {
            return null;
        }

        $table = Table::with([
            'orders' => fn ($query) => $query
                ->whereIn('status', Table::ORDER_OPEN_STATUSES)
                ->with(['orderProducts.product', 'waiter'])
                ->orderBy('created_at', 'desc'),
            'reservations' => fn ($query) => $query
                ->whereIn('status', Table::RESERVATION_ACTIVE_STATUSES)
                ->where('reservation_time', '>', now())
                ->with('customer')
                ->orderBy('reservation_time', 'asc'),
            'waiter',
        ])->find($this->selectedTableId);

        if (! $table || $table->restaurant_id !== 1) {
            return null;
        }

        $zone = $this->normalizeZone($table->location);
        $activeOrders = $table->orders;
        $firstOrder = $activeOrders->first();
        $nextReservation = $table->reservations->first();

        return [
            'id' => $table->id,
            'number' => $table->number,
            'zone' => $zone,
            'zone_label' => self::ZONE_LABELS[$zone],
            'location' => $table->location,
            'capacity' => $table->capacity,
            'status' => $table->status,
            'status_label' => $this->statusLabel($table),
            'waiter_name' => $table->waiter?->name ?? $firstOrder?->waiter?->name ?? 'Sin asignar',
            'permanence' => $firstOrder?->created_at->diffForHumans(null, true),
            'orders_count' => $activeOrders->count(),
            'total_amount' => $this->tablesTotal($activeOrders),
            'first_order_id' => $firstOrder?->id,
            'has_reservation' => $nextReservation !== null,
            'reservation_info' => $nextReservation ? $this->reservationInfo($nextReservation) : null,
            'orders' => $activeOrders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'status' => $order->status,
                    'created_at' => $order->created_at->diffForHumans(),
                    'created_time' => $order->created_at->format('H:i'),
                    'subtotal' => round($order->orderProducts->sum(function ($item) {
                        return $item->quantity * $item->price;
                    }), 2),
                    'products' => $order->orderProducts->map(function ($item) {
                        return [
                            'name' => $item->product?->name ?? 'Producto',
                            'quantity' => $item->quantity,
                            'price' => (float) $item->price,
                        ];
                    })->toArray(),
                ];
            })->toArray(),
        ];
    }

    /**
     * ¿La mesa coincide con el buscador? Filtra por número de mesa,
     * nombre del comensal reservado e info de la reserva.
     */
    public function tableMatchesSearch(array $table): bool
    {
        if ($this->search === '') {
            return true;
        }

        $query = mb_strtolower(trim($this->search));

        $haystack = mb_strtolower(implode(' ', [
            (string) $table['number'],
            (string) ($table['waiter_name'] ?? ''),
            (string) ($table['reservation_info'] ?? ''),
        ]));

        return str_contains($haystack, $query);
    }

    // ¿El usuario actual es super_admin? (para habilitar "Cambiar Estado")
    public function isSuperAdmin(): bool
    {
        return (bool) (auth()->user()?->hasRole('super_admin'));
    }

    /**
     * Normalizar la location de la mesa (legacy en español) a una clave de zona
     * consistente: lowercase sin tildes → terraza | salon | barra.
     */
    private function normalizeZone(?string $location): string
    {
        $key = strtolower(Str::ascii(trim((string) $location)));

        return match ($key) {
            'terraza', 'exterior', 'patio' => 'terraza',
            'barra', 'bar' => 'barra',
            // 'salon', 'interior', 'vip', 'ventana', 'comedor' y cualquier otra
            // ubicación no mapeada caen en 'salon' para no romper el plano.
            default => 'salon',
        };
    }

    /**
     * Etiqueta legible del estado para el panel de detalle.
     */
    private function statusLabel(Table $table): string
    {
        return match ($table->status) {
            Table::STATUS_AVAILABLE => 'Disponible',
            Table::STATUS_OCCUPIED => 'Ocupada ('.$table->capacity.' pax)',
            Table::STATUS_RESERVED => 'Reservada',
            Table::STATUS_MAINTENANCE => 'Mantenimiento',
            default => ucfirst((string) $table->status),
        };
    }

    /**
     * Info de la próxima reserva: "Familia Gómez (21:30h)".
     */
    private function reservationInfo(Reservation $reservation): string
    {
        $customerName = $reservation->customer?->name ?? 'Cliente';

        return "{$customerName} ({$reservation->reservation_time->format('H:i')}h)";
    }

    /**
     * Total de una colección de pedidos activos: suma quantity * price
     * de todos sus ítems (Order no tiene columna total).
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Order>  $orders
     */
    private function tablesTotal($orders): float
    {
        return round($orders->sum(function ($order) {
            return $order->orderProducts->sum(function ($item) {
                return $item->quantity * $item->price;
            });
        }), 2);
    }

    // Acciones del header
    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refrescar')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn () => $this->loadTables())
                ->keyBindings(['f5']),
        ];
    }
}
