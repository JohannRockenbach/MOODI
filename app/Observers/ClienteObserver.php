<?php

namespace App\Observers;

use App\Models\Cliente;
use App\Services\LoyaltyPromoService;

class ClienteObserver
{
    public function __construct(private LoyaltyPromoService $loyaltyPromoService)
    {
        //
    }

    /**
     * Handle the Cliente "created" event.
     *
     * Disparo inmediato del proceso de fidelización: si el cumpleaños del
     * cliente cae HOY, la promoción de cumpleaños se dispara al instante,
     * sin esperar al chequeo diario del scheduler.
     *
     * El servicio es seguro por diseño: con birthday null, cumpleaños que no
     * cae hoy o sin admins super_admin, simplemente no notifica.
     */
    public function created(Cliente $cliente): void
    {
        $this->loyaltyPromoService->notifyBirthday($cliente);
    }
}