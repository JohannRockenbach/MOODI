<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\Caja;

class CajaBalance extends Component
{
    public int $cajaId;
    public ?float $balance = null;

    public function mount(int $cajaId)
    {
        $this->cajaId = $cajaId;
        $this->refreshBalance();
    }

    public function refreshBalance(): void
    {
        $caja = Caja::find($this->cajaId);
        if (! $caja) {
            $this->balance = null;
            return;
        }

        $totalSales = $caja->sales()->sum('total_amount');
        $val = (float) ($caja->initial_balance + $totalSales);
        // Safety cap: if value is unreasonably large, keep it but mark it
        $this->balance = $val;
    }

    public function render()
    {
        return view('livewire.caja-balance');
    }
}
