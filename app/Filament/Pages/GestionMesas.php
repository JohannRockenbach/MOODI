<?php

namespace App\Filament\Pages;

use App\Models\Table;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;

/**
 * GestionMesas — listado de mesas estilo Stitch (Negro Neutro y Ámbar).
 *
 * Reemplaza la tabla estándar de TableResource manteniendo TODA la lógica de
 * negocio del dominio: la creación respeta la unicidad de número por
 * restaurante y las zonas normalizadas (TableMap::ZONE_LABELS), la liberación
 * usa la máquina de estados Table::release() (que respeta reservas futuras) y
 * el cambio de estado replica TableMap::toggleTableStatus (solo super_admin y
 * sin pedidos activos/ocupada/reservada).
 *
 * La edición/creación de detalle NO se reimplementa: redirige al form del
 * TableResource (validaciones originales). La eliminación agrega la validación
 * que faltaba: se rechaza si la mesa tiene pedidos o reservas activas.
 */
class GestionMesas extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static string $view = 'filament.pages.gestion-mesas';

    /**
     * Listado operativo a ancho completo, igual que TableMap (BasePage
     * consume getMaxContentWidth() → 'max-w-full').
     */
    protected ?string $maxContentWidth = MaxWidth::Full->value;

    protected static ?string $title = 'Mesas';

    protected static ?string $navigationLabel = 'Mesas';

    protected static ?string $navigationGroup = 'Operaciones del Salón';

    protected static ?int $navigationSort = 1;

    /**
     * Claves normalizadas de zona y su etiqueta de display. Misma fuente de
     * verdad que TableMap para que el listado agrupe igual que el plano.
     */
    public const ZONE_LABELS = TableMap::ZONE_LABELS;

    // KPIs del header.
    public array $stats = [
        'available' => 0,
        'occupied' => 0,
        'reserved' => 0,
        'maintenance' => 0,
    ];

    // Mesas enriquecidas para el grid.
    public array $tables = [];

    // Buscador y filtros del header.
    public string $search = '';

    public string $activeZone = 'all';

    public string $activeStatus = 'all';

    // Propiedades del modal "Nueva Mesa" (patrón TableMap).
    public string $newNumber = '';

    public string $newLocation = 'terraza';

    public $newCapacity = 4;

    /**
     * Acceso: super_admin, Mozo y Cajero — el mismo conjunto que
     * TablePolicy::viewAny.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->hasAnyRole(['super_admin', 'Mozo', 'Cajero']);
    }

    public function mount(): void
    {
        $this->loadTables();
    }

    /**
     * Cargar las mesas del restaurante (id 1) enriquecidas con zona
     * normalizada, pedidos activos, próxima reserva y mozo asignado.
     */
    public function loadTables(): void
    {
        $tables = Table::where('restaurant_id', 1)
            ->with([
                'waiter',
                'orders' => fn ($query) => $query->whereIn('status', Table::ORDER_OPEN_STATUSES),
                'reservations' => fn ($query) => $query
                    ->whereIn('status', Table::RESERVATION_ACTIVE_STATUSES)
                    ->where('reservation_time', '>', now())
                    ->orderBy('reservation_time', 'asc'),
            ])
            ->orderBy('number')
            ->get();

        $this->tables = $tables
            ->map(function (Table $table): array {
                $zone = $this->normalizeZone($table->location);
                $nextReservation = $table->reservations->first();

                return [
                    'id' => $table->id,
                    'number' => $table->number,
                    'location' => $table->location,
                    'zone' => $zone,
                    'zone_label' => self::ZONE_LABELS[$zone],
                    'capacity' => $table->capacity,
                    'status' => $table->status,
                    'status_label' => $this->statusLabel($table->status),
                    'waiter_name' => $table->waiter?->name ?? 'Sin asignar',
                    'active_orders' => $table->orders->count(),
                    'next_reservation' => $nextReservation
                        ? $nextReservation->reservation_time->format('H:i')
                        : null,
                ];
            })
            ->values()
            ->all();

        $this->stats = [
            'available' => $tables->where('status', Table::STATUS_AVAILABLE)->count(),
            'occupied' => $tables->where('status', Table::STATUS_OCCUPIED)->count(),
            'reserved' => $tables->where('status', Table::STATUS_RESERVED)->count(),
            'maintenance' => $tables->where('status', Table::STATUS_MAINTENANCE)->count(),
        ];

        // Invalidar el computed para que el grid no sirva datos viejos si se
        // re-ejecuta loadTables en el mismo ciclo de request.
        unset($this->visibleTables);
    }

    /**
     * Mesas visibles según los filtros activos (zona, estado y búsqueda por
     * número/mozo/ubicación). El blade itera SOLO esta colección para que el
     * morph de Livewire nunca deje bloques huérfanos.
     */
    #[Computed]
    public function visibleTables(): array
    {
        return collect($this->tables)
            ->filter(fn (array $table): bool => $this->activeZone === 'all' || $table['zone'] === $this->activeZone)
            ->filter(fn (array $table): bool => $this->activeStatus === 'all' || $table['status'] === $this->activeStatus)
            ->filter(fn (array $table): bool => $this->tableMatchesSearch($table))
            ->values()
            ->all();
    }

    /**
     * ¿La mesa coincide con el buscador? Filtra por número, mozo asignado y
     * ubicación (case-insensitive).
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
            (string) ($table['location'] ?? ''),
            (string) ($table['zone_label'] ?? ''),
        ]));

        return str_contains($haystack, $query);
    }

    /**
     * ¿El usuario actual es super_admin? (habilita Cambiar Estado y Eliminar).
     */
    public function isSuperAdmin(): bool
    {
        return (bool) (auth()->user()?->hasRole('super_admin'));
    }

    /**
     * Crear una mesa nueva desde el modal "Nueva Mesa". Validaciones
     * idénticas a TableMap: número único por restaurante, zona normalizada y
     * capacidad 1-20. La location se persiste con el label de display
     * (formato legacy español que espera el resto de la app).
     */
    public function createTable(): void
    {
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
            // Defensa en profundidad (mismo criterio que TableMap): persistir
            // coordenadas 0 para no depender del default de DB.
            'pos_x' => 0,
            'pos_y' => 0,
        ]);

        $this->reset('newNumber', 'newCapacity');
        $this->newLocation = 'terraza';

        Notification::make()
            ->title('Mesa creada')
            ->body("Mesa {$table->number} ({$table->capacity} pax) dada de alta correctamente")
            ->success()
            ->send();

        $this->loadTables();
    }

    /**
     * Editar mesa: redirige al form del TableResource (conserva el formulario
     * con todas sus validaciones; no se reimplementa nada acá).
     */
    public function editTable(int $tableId): void
    {
        $this->redirect(
            \App\Filament\Resources\TableResource::getUrl('edit', ['record' => $tableId])
        );
    }

    /**
     * Cambiar estado de la mesa (toggle disponible ↔ mantenimiento). Solo
     * super_admin y solo si la mesa no está ocupada/reservada ni tiene
     * pedidos activos. Misma lógica que TableMap::toggleTableStatus.
     */
    public function toggleTableStatus(int $tableId): void
    {
        if (! $this->isSuperAdmin()) {
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
     * Liberar la mesa con la máquina de estados centralizada: release()
     * deniega con pedidos activos y respeta reservas futuras vigentes
     * (destino 'reserved' en vez de 'available'). Misma lógica que
     * TableMap::freeTable.
     */
    public function freeTable(int $tableId): void
    {
        $table = Table::with('orders')->find($tableId);

        if (! $table || $table->restaurant_id !== 1) {
            return;
        }

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

        if ($table->status === Table::STATUS_OCCUPIED) {
            $table->release();
        } elseif ($table->status === Table::STATUS_RESERVED && ! $table->hasFutureActiveReservations()) {
            $table->update(['status' => Table::STATUS_AVAILABLE]);
        }

        Notification::make()
            ->title('Mesa Liberada')
            ->body("Mesa #{$table->number} ahora está disponible")
            ->success()
            ->send();

        $this->loadTables();
    }

    /**
     * Eliminar mesa. Solo super_admin. VALIDACIÓN NUEVA (antes faltaba):
     * se rechaza si la mesa tiene pedidos (cualquiera, por integridad de
     * datos como TableResource DeleteAction) o reservas ACTIVAS
     * (pending/confirmed). Las reservas canceladas no bloquean.
     */
    public function deleteTable(int $tableId): void
    {
        if (! $this->isSuperAdmin()) {
            Notification::make()
                ->title('Permiso denegado')
                ->body('Solo el super_admin puede eliminar mesas.')
                ->warning()
                ->send();

            return;
        }

        $table = Table::withCount([
            'orders',
            'reservations' => fn ($query) => $query->whereIn('status', Table::RESERVATION_ACTIVE_STATUSES),
        ])->find($tableId);

        if (! $table || $table->restaurant_id !== 1) {
            return;
        }

        if ($table->orders_count > 0 || $table->reservations_count > 0) {
            Notification::make()
                ->title('No se puede eliminar')
                ->body('No se puede eliminar: la mesa tiene pedidos o reservas asociados.')
                ->danger()
                ->send();

            return;
        }

        $number = $table->number;
        $table->delete();

        Notification::make()
            ->title('Mesa eliminada')
            ->body("La mesa #{$number} fue eliminada correctamente.")
            ->success()
            ->send();

        $this->loadTables();
    }

    /**
     * Normalizar la location de la mesa (legacy en español) a una clave de
     * zona consistente. Misma normalización que TableMap.
     */
    private function normalizeZone(?string $location): string
    {
        $key = strtolower(Str::ascii(trim((string) $location)));

        return match ($key) {
            'terraza', 'exterior', 'patio' => 'terraza',
            'barra', 'bar' => 'barra',
            default => 'salon',
        };
    }

    /**
     * Etiqueta legible del estado de una mesa.
     */
    private function statusLabel(string $status): string
    {
        return match ($status) {
            Table::STATUS_AVAILABLE => 'Disponible',
            Table::STATUS_OCCUPIED => 'Ocupada',
            Table::STATUS_RESERVED => 'Reservada',
            Table::STATUS_MAINTENANCE => 'Mantenimiento',
            default => ucfirst($status),
        };
    }

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