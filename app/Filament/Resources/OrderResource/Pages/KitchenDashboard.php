<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\User;
use App\Services\AuditService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\MaxWidth;

class KitchenDashboard extends Page
{
    protected static string $resource = OrderResource::class;

    // Pantalla operativa a ancho completo, igual que el TPV (Crear Pedido / Mapa).
    protected ?string $maxContentWidth = MaxWidth::Full->value;

    // Usar ruta de vista concisa bajo resources/views/filament/pages
    protected static string $view = 'filament.pages.kitchen-dashboard';

    // La pantalla de cocina es operativa: super_admin y Cocinero.
    public static function canAccess(array $parameters = []): bool
    {
        $user = \Illuminate\Support\Facades\Auth::user();

        return $user !== null && $user->hasAnyRole(['super_admin', 'Cocinero']);
    }

    /**
     * El KDS se autoriza con SU PROPIA canAccess (super_admin/Cocinero), no
     * con la del recurso completo. OrderResource::canAccess() (= canViewAny)
     * excluye al Cocinero desde la restricción de roles (el Cocinero no ve el
     * listado de pedidos), pero DEBE poder abrir la cocina.
     */
    public static function authorizeResourceAccess(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    // Propiedades públicas expuestas a la vista / Livewire
    public $pendingOrders;

    public $processingOrders;

    public $readyOrders;

    public $lowStockIngredients;

    // Texto del problema de stock (panel "Reportar problema de stock").
    public string $stockProblemText = '';

    // Filament / Livewire hará polling al componente. Retorna segundos.
    protected function getPollingInterval(): ?string
    {
        return '5s'; // Actualiza cada 5 segundos
    }

    public function mount(): void
    {
        $this->loadOrders();
    }

    public function loadOrders(): void
    {
        $bebidasCategoryId = \App\Models\Category::where('name', 'Bebidas')->first()?->id;

        /** @var \App\Models\User|null $user */
        $user = \Illuminate\Support\Facades\Auth::user();
        $isSuperAdmin = $user && $user->hasRole('super_admin');
        $restaurantId = $user?->restaurant_id;

        $baseQuery = Order::with(['orderProducts.product.category', 'table']);

        if (! $isSuperAdmin && $restaurantId) {
            $baseQuery->where('restaurant_id', $restaurantId);
        }

        // Si hay categoría "Bebidas", filtramos
        if ($bebidasCategoryId) {
            $baseQuery->whereHas('orderProducts.product', function ($q) use ($bebidasCategoryId) {
                $q->where('category_id', '!=', $bebidasCategoryId);
            });
        }

        // Cargar las dos listas
        // Pendientes: más recientes primero (para ver los nuevos arriba)
        $this->pendingOrders = (clone $baseQuery)->where('status', 'pending')->latest()->get();

        // En Preparación: más antiguos primero (FIFO - First In, First Out)
        // Los que entraron primero deben salir primero
        $this->processingOrders = (clone $baseQuery)->where('status', 'processing')->oldest()->get();

        // Listos para retirar: más antiguos primero
        $this->readyOrders = (clone $baseQuery)->where('status', 'ready_for_pickup')->oldest()->get();

        $this->loadLowStockIngredients();
    }

    /**
     * Ingredientes con stock bajo (<= min_stock) para el panel de reposición.
     * El stock real se calcula desde los lotes (availableStock = lotes NO
     * vencidos), misma semántica que el LowStockWidget del dashboard.
     */
    protected function loadLowStockIngredients(): void
    {
        /** @var \App\Models\User|null $user */
        $user = \Illuminate\Support\Facades\Auth::user();
        $isSuperAdmin = $user && $user->hasRole('super_admin');
        $restaurantId = $user?->restaurant_id;

        $this->lowStockIngredients = Ingredient::query()
            ->with('batches')
            ->when(! $isSuperAdmin && $restaurantId, fn ($q) => $q->where('restaurant_id', $restaurantId))
            ->get()
            ->filter(fn (Ingredient $ingredient) => $ingredient->availableStock() <= (float) $ingredient->min_stock)
            ->sortBy('name')
            ->values();
    }

    // Acción disparada desde la UI para comenzar a procesar un pedido
    public function startProcessing(int $orderId): void
    {
        $order = Order::find($orderId);
        if ($order && $order->status === 'pending') {
            $order->update(['status' => 'processing']);
            $this->loadOrders(); // Recargar AMBAS listas
            $this->notify('success', 'Pedido movido a En Preparación.');
            $this->dispatch('orderStatusChanged');
        }
    }

    // Nuevo método para marcar pedido como listo
    public function markAsReady(int $orderId): void
    {
        $order = Order::find($orderId);
        if ($order && $order->status === 'processing') {
            $order->update(['status' => 'ready_for_pickup']);
            $this->loadOrders();

            \Filament\Notifications\Notification::make()
                ->success()
                ->title('🟠 Pedido Listo')
                ->body("Pedido #{$orderId} está listo para retirar.")
                ->icon('heroicon-o-check-circle')
                ->iconColor('success')
                ->send();

            $this->dispatch('orderStatusChanged');
        }
    }

    // Marcar pedido como completado (desde ready_for_pickup)
    public function markAsCompleted(int $orderId): void
    {
        $order = Order::find($orderId);
        if ($order && $order->status === 'ready_for_pickup') {
            $order->update(['status' => 'completed']);
            $this->loadOrders();
            $this->notify('success', 'Pedido marcado como Completado.');
            $this->dispatch('orderStatusChanged');
        }
    }

    // Nuevo método para cancelar pedido
    public function cancelOrder(int $orderId): void
    {
        $order = Order::find($orderId);
        if ($order && in_array($order->status, ['pending', 'processing'])) {
            $previousStatus = $order->status;
            $order->update(['status' => 'cancelled']);
            $this->loadOrders(); // Recargar AMBAS listas

            // Notificación diferenciada según el estado previo
            if ($previousStatus === 'pending') {
                \Filament\Notifications\Notification::make()
                    ->success()
                    ->title('Pedido Cancelado')
                    ->body("El pedido #{$orderId} ha sido cancelado correctamente.")
                    ->icon('heroicon-o-check-circle')
                    ->iconColor('success')
                    ->send();
            } else {
                \Filament\Notifications\Notification::make()
                    ->warning()
                    ->title('Pedido Cancelado')
                    ->body("El pedido #{$orderId} fue cancelado. Los ingredientes ya usados se registran como merma.")
                    ->icon('heroicon-o-exclamation-triangle')
                    ->iconColor('warning')
                    ->send();
            }

            $this->dispatch('orderStatusChanged');
        }
    }

    // ─══════════════════════════════════════════════════════════════
    // CU-008.3 — Reportar retraso de un pedido en producción.
    // CU-008.6 — Reportar problema de stock.
    // CU-008.7 — Solicitar reposición de un ingrediente con stock bajo.
    // ─══════════════════════════════════════════════════════════════

    /**
     * Reportar retraso (CU-008.3). Solo pedidos en producción pendiente
     * (pending / processing): notifica a los super_admin por DB y audita.
     */
    public function reportDelay(int $orderId): void
    {
        $order = Order::find($orderId);

        if (! $order || ! in_array($order->status, ['pending', 'processing'], true)) {
            return; // Pedido inexistente o fuera de producción: no hace nada.
        }

        $subject = $this->orderSubjectLabel($order);
        $status = $this->statusLabel($order->status);

        $this->sendToSuperAdmins(
            Notification::make()
                ->warning()
                ->title("Retraso en pedido #{$order->id}")
                ->body("{$subject} — Estado: {$status}.")
                ->icon('heroicon-o-clock')
                ->iconColor('warning')
        );

        AuditService::record('kitchen.delay_reported', $order, ['order_id' => $order->id]);

        $this->notify('success', 'Retraso reportado.');
    }

    /**
     * Reportar problema de stock (CU-008.6). El Cocinero escribe un detalle
     * breve en el panel del KDS; si el texto es inválido (menos de 3
     * caracteres) se muestra un error local y NO se envía nada.
     */
    public function reportStockProblem(): void
    {
        $detail = trim($this->stockProblemText);

        if (mb_strlen($detail) < 3) {
            $this->notify('danger', 'Escribí un detalle del problema (mínimo 3 caracteres).');

            return;
        }

        $this->sendToSuperAdmins(
            Notification::make()
                ->danger()
                ->title('Problema de stock reportado')
                ->body($detail)
                ->icon('heroicon-o-exclamation-triangle')
                ->iconColor('danger')
        );

        AuditService::record('kitchen.stock_problem', null, ['detail' => $detail]);

        $this->stockProblemText = '';
        $this->notify('success', 'Problema de stock reportado.');
    }

    /**
     * Solicitar reposición (CU-008.7) de un ingrediente con stock bajo.
     * Ingrediente inexistente: no rompe y no envía nada.
     */
    public function requestRestock(int $ingredientId): void
    {
        $ingredient = Ingredient::find($ingredientId);

        if (! $ingredient) {
            return;
        }

        $stock = $ingredient->availableStock();
        $unit = (string) $ingredient->measurement_unit;

        $this->sendToSuperAdmins(
            Notification::make()
                ->warning()
                ->title("Solicitud de reposición: {$ingredient->name} (stock actual {$stock})")
                ->body($unit !== '' ? "Unidad: {$unit}." : '')
                ->icon('heroicon-o-arrow-path')
                ->iconColor('warning')
        );

        AuditService::record('kitchen.restock_requested', $ingredient, ['stock' => $stock]);

        $this->notify('success', 'Solicitud de reposición enviada.');
    }

    /**
     * Envía una notificación Filament DB a TODOS los super_admin (no hay una
     * sesión única en el KDS: el destinatario es el rol, no el navegador).
     */
    protected function sendToSuperAdmins(Notification $notification): void
    {
        $admins = User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->get();

        foreach ($admins as $admin) {
            $notification->sendToDatabase($admin);
        }
    }

    protected function orderSubjectLabel(Order $order): string
    {
        return match ($order->type) {
            'salon' => 'Mesa #'.($order->table->number ?? 'N/A'),
            'para_llevar' => 'Para llevar',
            'delivery' => 'Delivery',
            default => 'Pedido',
        };
    }

    protected function statusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Pendiente',
            'processing' => 'En Preparación',
            default => ucfirst($status),
        };
    }

    /**
     * CU-008 — división por sector de producción: agrupa los orderProducts de
     * un pedido por su categoría (product.category.name), excluyendo "Bebidas"
     * y preservando el orden de aparición. Solo presentación, no cambia estados.
     *
     * @return array<string, list<\App\Models\OrderProduct>>
     */
    public function orderProductsBySector(Order $order): array
    {
        $sectors = [];

        foreach ($order->orderProducts as $item) {
            $categoryName = $item->product?->category?->name;

            if ($categoryName === null || $categoryName === 'Bebidas') {
                continue;
            }

            $sectors[$categoryName][] = $item;
        }

        return $sectors;
    }

    /**
     * Emoji de cabecera para un sector de producción (fallback 🍽️).
     */
    public function sectorEmoji(string $sector): string
    {
        return match (mb_strtolower(trim($sector))) {
            'hamburguesas', 'clásicas', 'especiales', 'veggie' => '🍔',
            'pizzas', 'calzones' => '🍕',
            'acompañamientos / frituras', 'papas fritas' => '🍟',
            'minutas / otros' => '🍽️',
            'postres' => '🍰',
            'ensaladas' => '🥗',
            'adicionales / extras' => '➕',
            'combos / promos' => '🎁',
            default => '🍽️',
        };
    }

    protected function notify(string $level, string $message): void
    {
        \Filament\Notifications\Notification::make()
            ->{$level}()
            ->title($message)
            ->send();
    }
}
