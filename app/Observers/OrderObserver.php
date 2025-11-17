<?php

namespace App\Observers;

use App\Models\Order;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        // Comprobar si el campo 'status' fue el que cambió
        if ($order->wasChanged('status')) {

            // Gatillo de DESCUENTO de stock
            if ($order->status === 'processing') {
                Log::info('--- DEBUG (Observer): Estado cambiado a PROCESSING. Disparando evento OrderProcessing. ---');
                \App\Events\OrderProcessing::dispatch($order);
            }
            
            // NOTA: Basado en la lógica de negocio, NO creamos
            // un listener para 'cancelled' porque el stock
            // en 'processing' se considera merma (desperdicio).
        }
    }
}
