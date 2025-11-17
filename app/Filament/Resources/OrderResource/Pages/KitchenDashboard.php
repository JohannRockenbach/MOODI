<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Models\Category;
use Filament\Resources\Pages\Page;

class KitchenDashboard extends Page
{
    protected static string $resource = OrderResource::class;

    // Usar ruta de vista concisa bajo resources/views/filament/pages
    protected static string $view = 'filament.pages.kitchen-dashboard';

    // Propiedades públicas expuestas a la vista / Livewire
    public $pendingOrders;
    public $processingOrders;

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

        $baseQuery = Order::where('restaurant_id', 1)
            ->with(['orderProducts.product.category', 'table']);

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
            // Marcar directamente como completado desde la cocina
            $order->update(['status' => 'completed']);
            $this->loadOrders(); // Recargar AMBAS listas
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

    protected function notify(string $level, string $message): void
    {
        \Filament\Notifications\Notification::make()
            ->{$level}()
            ->title($message)
            ->send();
    }
}
