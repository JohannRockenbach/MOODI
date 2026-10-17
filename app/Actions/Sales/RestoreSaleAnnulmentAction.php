<?php

namespace App\Actions\Sales;

use App\Models\Sale;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\Gate;

class RestoreSaleAnnulmentAction
{
    public function __invoke(Sale $sale, User $user): Sale
    {
        Gate::forUser($user)->authorize('restoreAnnulled', $sale);

        // Capturar el motivo previo ANTES de restaurar (lo limpia el modelo).
        $previousReason = $sale->annulled_reason;

        $restored = $sale->restoreAnnulment();

        // Auditoría (TAREA 2.1): punto único de restauración, con usuario.
        AuditService::record('sale.restored', $restored, [
            'motivo_previo' => $previousReason,
        ], $user);

        return $restored;
    }
}