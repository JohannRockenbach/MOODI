<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Discount extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'type',
        'value',
        'is_active',
        'expires_at',
        'restaurant_id',
        'starts_at',
        'ends_at',
        'days_of_week',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
            // days_of_week: JSON array 0=Lunes..6=Domingo. null = todos los días.
            'days_of_week' => 'array',
        ];
    }

    /**
     * ¿El descuento es elegible AHORA? Evalúa, en orden:
     *   1. is_active.
     *   2. No expirado (expires_at > now).
     *   3. Día de la semana (days_of_week 0=Lun..6=Dom; null/vacío = todos).
     *   4. Franja horaria (starts_at/ends_at; null en ambos = sin restricción).
     *
     * DECISIÓN DEL ORQUESTADOR (OBJ-07, ajustable por el dueño):
     *   - Convención de días: 0=Lunes..6=Domingo (ISO-8601, 0-based). Se mapea
     *     desde Carbon::$dayOfWeekIso (1=Lun..7=Dom) restándole 1.
     *   - starts_at/ends_at se comparan como minutos-desde-medianoche; NO se
     *     soporta franja que cruza medianoche (ends_at < starts_at → nunca
     *     elegible). Documentado como limitación.
     */
    public function isEligibleNow(?Carbon $now = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now ??= now();

        if ($this->expires_at && $now->greaterThan($this->expires_at)) {
            return false;
        }

        $days = $this->days_of_week;

        if (is_array($days) && $days !== []) {
            $today = ((int) $now->dayOfWeekIso) - 1; // 0=Lun..6=Dom
            if (! in_array($today, array_map('intval', $days), true)) {
                return false;
            }
        }

        if ($this->starts_at || $this->ends_at) {
            $current = ((int) $now->format('H')) * 60 + (int) $now->format('i');

            if ($this->starts_at && $current < $this->timeToMinutes($this->starts_at)) {
                return false;
            }

            if ($this->ends_at && $current > $this->timeToMinutes($this->ends_at)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Convierte una hora "HH:MM[:SS]" (time de Postgres o TimePicker de
     * Filament) a minutos desde medianoche. Robusto ante la ausencia de
     * segundos y ante cualquier prefijo/cast inesperado.
     */
    private function timeToMinutes(mixed $value): int
    {
        $parts = explode(':', (string) $value);

        return ((int) ($parts[0] ?? 0)) * 60 + (int) ($parts[1] ?? 0);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Un descuento puede ser aplicado en MUCHAS ventas.
     */
    public function sales(): BelongsToMany
    {
        return $this->belongsToMany(Sale::class)
                    ->withPivot('amount_discounted');
    }

    protected static function booted(): void
    {
        // Un descuento ya asociado a ventas NO se puede eliminar: rompería
        // el histórico contable (sale_discount.amount_discounted).
        static::deleting(function (Discount $discount): void {
            if ($discount->sales()->exists()) {
                throw new \DomainException(
                    'No se puede eliminar el descuento "'.$discount->name.'" porque está asociado a ventas registradas. Desactívalo en su lugar.'
                );
            }
        });
    }
}