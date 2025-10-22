<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'cae_number',
        'customer_data',
        'sale_id',
    ];

    protected function casts(): array
    {
        return [
            'customer_data' => 'array', // Tratar la columna JSON como un array asociativo.
        ];
    }
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}