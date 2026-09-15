<?php

namespace App\Filament\Pages;

use App\Models\Table;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TableMap extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static string $view = 'filament.pages.table-map';
    protected static ?string $title = 'Mapa de Mesas';
    protected static ?string $navigationLabel = 'Mapa de Mesas';
    protected static ?string $navigationGroup = 'Operaciones del Salón';
    protected static ?int $navigationSort = 1;

    // Propiedades principales
    public array $tablesByLocation = [];
    public array $stats = [
        'available' => 0,
        'occupied' => 0,
        'reserved' => 0,
    ];
    public ?int $selectedTableId = null;

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

    // Cargar las mesas agrupadas por ubicación
    #[On('refresh-map')]
    public function loadTables(): void
    {
        // Obtener todas las mesas con sus pedidos activos
        $tables = Table::where('restaurant_id', 1)
            ->with(['orders' => function($query) {
                $query->whereNotIn('status', ['cancelled', 'completed'])
                      ->with('orderProducts.product')
                      ->orderBy('created_at', 'desc');
            }])
            ->orderBy('number')
            ->get();

        // Agrupar por ubicación
        $this->tablesByLocation = $tables->groupBy('location')->map(function($locationTables) {
            return $locationTables->map(function($table) {
                $activeOrders = $table->orders;
                $firstOrder = $activeOrders->first();

                return [
                    'id' => $table->id,
                    'number' => $table->number,
                    'location' => $table->location,
                    'capacity' => $table->capacity,
                    'status' => $table->status,
                    'orders_count' => $activeOrders->count(),
                    'total_amount' => $activeOrders->sum('total'),
                    'elapsed_time' => $firstOrder ? $firstOrder->created_at->diffForHumans(null, true) : null,
                    'first_order_id' => $firstOrder ? $firstOrder->id : null,
                ];
            })->values();
        })->toArray();

        // Calcular estadísticas globales
        $this->stats = [
            'available' => $tables->where('status', 'available')->count(),
            'occupied' => $tables->where('status', 'occupied')->count(),
            'reserved' => $tables->where('status', 'reserved')->count(),
        ];
    }

    // Abrir una mesa (MOSTRAR MODAL, no redirigir)
    public function openTable(int $tableId): void
    {
        $this->selectedTableId = $tableId;
        $this->loadTables(); // Refrescar datos
        $this->dispatch('open-modal', id: 'table-details');
    }

    // Cerrar modal
    public function closeTableModal(): void
    {
        $this->selectedTableId = null;
        $this->dispatch('close-modal', id: 'table-details');
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

        if (!$this->selectedTableId) {
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
                'from_map' => 1  // 🔒 Indicar que viene del mapa para bloquear campos
            ])
        );
    }

    // Liberar una mesa
    public function freeTable(int $tableId): void
    {
        $this->authorizeStaffAccess();

        $table = Table::with('orders')->find($tableId);
        
        if (!$table) {
            return;
        }

        // Verificar que no haya pedidos activos
        $hasActiveOrders = $table->orders()
            ->whereNotIn('status', ['cancelled', 'completed'])
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

    // Preparar datos para cobrar la mesa
    public function prepareCobroMesa(int $tableId): void
    {
        $table = Table::with(['orders' => function($query) {
            $query->whereNotIn('status', ['cancelled', 'completed'])
                  ->with('orderProducts');
        }])->find($tableId);

        if (!$table || $table->orders->isEmpty()) {
            Notification::make()
                ->title('Error')
                ->body('No hay pedidos activos para cobrar en esta mesa')
                ->danger()
                ->send();
            return;
        }

        // Calcular total de todos los pedidos
        $total = $table->orders->sum(function($order) {
            return $order->orderProducts->sum(function($item) {
                return $item->quantity * $item->price;
            });
        });

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

        if (!$this->selectedTableId) {
            return;
        }

        $table = Table::with(['orders' => function($query) {
            $query->whereNotIn('status', ['cancelled', 'completed'])
                  ->with('orderProducts');
        }])->find($this->selectedTableId);

        if (!$table || $table->orders->isEmpty()) {
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

        if (!$cajaAbierta) {
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
                    $orderTotal = $order->orderProducts->sum(function($item) {
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
                    if (!empty($this->selectedDiscounts)) {
                        foreach ($this->selectedDiscounts as $discountId) {
                            $discount = \App\Models\Discount::where('id', $discountId)
                                ->where('is_active', true)
                                ->first();
                            if ($discount) {
                                $discountValue = $discount->type === 'percentage'
                                    ? $orderTotal * ($discount->value / 100)
                                    : ($orderTotal / $this->totalAmount) * $discount->value;

                                $sale->discounts()->attach($discountId, [
                                    'amount_discounted' => $discountValue
                                ]);
                            }
                        }
                    }

                    // 🔒 El pedido cobrado deja de estar activo: pasa a 'completed'.
                    // Sin esto, el mapa seguiría mostrando pedidos activos en la mesa
                    // y la mesa no se liberaría correctamente tras el cobro.
                    $order->update(['status' => 'completed']);
                }

                // Liberar la mesa con la máquina de estados centralizada.
                // release() vuelve 'occupied' -> 'available' (o 'reserved' si hay
                // reservas futuras vigentes). Si la mesa no partió de 'occupied'
                // (estado legacy) no se toca nada.
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
            $this->dispatch('close-modal', id: 'table-details');
            
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

    // Propiedad computada: Obtener la mesa seleccionada con todos sus detalles
    #[Computed]
    public function selectedTable(): ?array
    {
        if (!$this->selectedTableId) {
            return null;
        }

        // Buscar en todas las ubicaciones
        foreach ($this->tablesByLocation as $location => $tables) {
            foreach ($tables as $table) {
                if ($table['id'] === $this->selectedTableId) {
                    // Obtener pedidos completos con productos
                    $tableModel = Table::with(['orders' => function($query) {
                        $query->whereNotIn('status', ['cancelled', 'completed'])
                              ->with('orderProducts.product')
                              ->orderBy('created_at', 'desc');
                    }])->find($table['id']);

                    if (!$tableModel) {
                        return $table;
                    }

                    // Enriquecer con datos completos de pedidos
                    $table['orders'] = $tableModel->orders->map(function($order) {
                        return [
                            'id' => $order->id,
                            'status' => $order->status,
                            'total' => $order->total ?? 0,
                            'created_at' => $order->created_at->diffForHumans(),
                            'created_time' => $order->created_at->format('H:i'),
                            'products' => $order->orderProducts->map(function($item) {
                                return [
                                    'name' => $item->product->name ?? 'Producto',
                                    'quantity' => $item->quantity,
                                    'price' => $item->price ?? 0,
                                ];
                            })->toArray(),
                        ];
                    })->toArray();

                    return $table;
                }
            }
        }

        return null;
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
