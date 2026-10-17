<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\Table;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    /**
     * Estados terminales: el pedido deja de mantener ocupada la mesa.
     */
    private const TERMINAL_STATUSES = ['completed', 'cancelled'];

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

            // Liberar la mesa cuando el pedido de salón deja de estar activo.
            // Cubre: toCompleted, toCancelled, cobro en lista (si luego se
            // completa), cancelación desde edición, etc.
            if (in_array($order->status, self::TERMINAL_STATUSES, true)) {
                $this->releaseTableIfLastActiveOrder($order);
            }
        }
    }

    /**
     * Handle the Order "deleted" event.
     */
    public function deleted(Order $order): void
    {
        // Si se borra un pedido de salón que estaba activo, reevaluar la mesa.
        if ($order->table_id && ! in_array($order->status, self::TERMINAL_STATUSES, true)) {
            $this->releaseTableIfLastActiveOrder($order);
        }
    }

    /**
     * Liberar la mesa asociada al pedido SOLO si este era el último pedido
     * activo. La transición la decide la máquina de estados centralizada
     * (Table::release()): 'occupied' -> 'available' o 'reserved'.
     */
    private function releaseTableIfLastActiveOrder(Order $order): void
    {
        if (! $order->table_id) {
            return;
        }

        $table = Table::find($order->table_id);

        if (! $table || $table->status !== Table::STATUS_OCCUPIED) {
            return;
        }

        // Si aún quedan pedidos activos en la mesa, no se libera.
        if ($table->hasActiveOrders()) {
            return;
        }

        $table->release();
    }
}
