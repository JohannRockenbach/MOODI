<?php

namespace App\Actions\Sales;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class AnnulSaleAction
{
    public function __invoke(Sale $sale, User $user, string $reason): Sale
    {
        Gate::forUser($user)->authorize('annul', $sale);

        return $sale->annul($user, $reason);
    }
}
