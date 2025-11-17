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
        
        // Validar que no se retroceda de completado
        if ($order->status === 'completed' && in_array($value, ['pending', 'processing'])) {
            Notification::make()
                ->danger()
                ->title('Acción no permitida')
                ->body('No se puede retroceder de un pedido completado.')
                ->send();
            
            // Revertir al estado anterior
            $this->status = $order->status;
            return;
        }
        
        // Guardar el nuevo estado
        $oldStatus = $order->status;
        $order->status = $value;
        $order->save();
        
        // Notificación de éxito
        Notification::make()
            ->success()
            ->title('Estado actualizado')
            ->body('Pedido #' . $order->id . ' → ' . match($value) {
                'pending' => '🟡 Pendiente',
                'processing' => '🔵 En Proceso',
                'completed' => '🟢 Completado',
                default => $value
            })
            ->send();
        
        // Emitir evento para refrescar la tabla padre
        $this->dispatch('order-updated');
    }
    
    public function render()
    {
        return view('livewire.order-status-selector');
    }
}
