<?php

namespace App\Actions\Sales;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class RestoreSaleAnnulmentAction
{
    public function __invoke(Sale $sale, User $user): Sale
    {
        Gate::forUser($user)->authorize('restoreAnnulled', $sale);

        return $sale->restoreAnnulment();
    }
}
