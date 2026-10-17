<?php

namespace App\Actions\Sales;

use App\Models\Sale;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\Gate;

class AnnulSaleAction
{
    public function __invoke(Sale $sale, User $user, string $reason): Sale
    {
        Gate::forUser($user)->authorize('annul', $sale);

        $annulled = $sale->annul($user, $reason);

        // Auditoría (TAREA 2.1): punto único de anulación, con motivo y usuario.
        AuditService::record('sale.annulled', $annulled, [
            'motivo' => $reason,
        ], $user);

        return $annulled;
    }
}