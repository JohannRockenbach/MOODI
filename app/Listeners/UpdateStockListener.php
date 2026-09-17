<?php

namespace App\Listeners;

use App\Events\OrderProcessing;
use App\Services\StockDeductionService;

class UpdateStockListener
{
    /**
     * Create the event listener.
     */
    public function __construct(private readonly StockDeductionService $stockDeductionService)
    {
        //
    }

    /**
     * Handle the event.
     * Descuenta el stock usando lógica FEFO (First Expired, First Out),
     * delegando en StockDeductionService (compartido con el TPV de cobro).
     */
    public function handle(OrderProcessing $event): void
    {
        $this->stockDeductionService->deductForOrder($event->order);
    }
}