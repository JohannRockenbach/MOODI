<?php

namespace App\Livewire;

use App\Models\Order;
use Livewire\Component;
use Filament\Notifications\Notification;

class OrderStatusSelector extends Component
{
    public $orderId;
    public $status;
    
    protected $listeners = ['refreshComponent' => '$refresh'];
    
    public function mount($orderId, $status)
    {
        $this->orderId = $orderId;
        $this->status = $status;
    }
    
    public function updatedStatus($value)
    {
        $order = Order::find($this->orderId);
        
        if (!$order) {
            $this->status = 'pending';
            return;
        }
        
        // Define valid transitions
        $validTransitions = [
            'pending' => ['processing', 'cancelled'],
            'processing' => ['ready_for_pickup', 'cancelled'],
            'ready_for_pickup' => ['completed'],
            'completed' => [],
            'cancelled' => [],
        ];
        
        $currentStatus = $order->status;
        $allowed = $validTransitions[$currentStatus] ?? [];
        
        if (!in_array($value, $allowed)) {
            Notification::make()
                ->danger()
                ->title('Transición no permitida')
                ->body("No se puede cambiar de '{$currentStatus}' a '{$value}'.")
                ->send();
            
            $this->status = $order->status;
            return;
        }
        
        $order->status = $value;
        $order->save();
        
        $statusLabels = [
            'pending' => '🟡 Pendiente',
            'processing' => '🔵 En Proceso',
            'ready_for_pickup' => '🟠 Listo para Retirar',
            'completed' => '🟢 Completado',
            'cancelled' => '🔴 Cancelado',
        ];
        
        Notification::make()
            ->success()
            ->title('Estado actualizado')
            ->body('Pedido #' . $order->id . ' → ' . ($statusLabels[$value] ?? $value))
            ->send();
        
        $this->dispatch('order-updated');
    }
    
    public function render()
    {
        $validTransitions = [
            'pending' => ['processing', 'cancelled'],
            'processing' => ['ready_for_pickup', 'cancelled'],
            'ready_for_pickup' => ['completed'],
            'completed' => [],
            'cancelled' => [],
        ];
        
        $currentStatus = $this->status;
        $allowedNext = $validTransitions[$currentStatus] ?? [];
        
        return view('livewire.order-status-selector', [
            'allowedStatuses' => array_merge([$currentStatus], $allowedNext),
        ]);
    }
}
