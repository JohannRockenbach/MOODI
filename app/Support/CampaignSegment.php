<?php

namespace App\Support;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Collection;

/**
 * Resolución de la audiencia objetivo (segmentos) para campañas de marketing.
 *
 * Single-restaurant: los clientes se filtran por restaurant_id cuando lo tienen
 * asignado; los registros legacy sin restaurant_id también se incluyen.
 *
 * Segmentos:
 *  - todos:      clientes con email no vacío.
 *  - cumpleanos: clientes que cumplen años durante el MES actual.
 *  - vip:        clientes con 5+ pedidos en los últimos 30 días.
 */
class CampaignSegment
{
    public const TODOS = 'todos';
    public const CUMPLEANOS = 'cumpleanos';
    public const VIP = 'vip';

    /**
     * Opciones disponibles para el Select de segmento.
     */
    public static function options(): array
    {
        return [
            self::TODOS => 'Todos los clientes',
            self::CUMPLEANOS => 'Cumpleañeros del mes',
            self::VIP => 'Clientes VIP (5+ pedidos en 30 días)',
        ];
    }

    /**
     * Valida que el segmento sea uno de los soportados.
     */
    public static function isValid(?string $segment): bool
    {
        return in_array($segment, [self::TODOS, self::CUMPLEANOS, self::VIP], true);
    }

    /**
     * Devuelve los Cliente destino para el segmento indicado.
     *
     * @param string|null $segment      Segmento objetivo (default 'todos').
     * @param int|null    $restaurantId Restaurant al que acotar la búsqueda.
     */
    public static function clientesForSegment(?string $segment = self::TODOS, ?int $restaurantId = null): Collection
    {
        $segment = self::isValid($segment) ? $segment : self::TODOS;

        $query = Cliente::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->when($restaurantId, function ($query) use ($restaurantId) {
                $query->where(fn ($filter) => $filter
                    ->whereNull('restaurant_id')
                    ->orWhere('restaurant_id', $restaurantId));
            });

        if ($segment === self::CUMPLEANOS) {
            // Cumpleañeros del MES: se documenta mes (no día) para campañas amplias.
            $query->whereMonth('birthday', now()->month);
        } elseif ($segment === self::VIP) {
            // Espejo de CheckLoyaltyPromo: 5+ pedidos en los últimos 30 días.
            $query->whereHas('orders', function ($query) {
                $query->where('created_at', '>=', now()->subDays(30));
            }, '>=', 5);
        }

        return $query->get();
    }

    /**
     * Texto legible del descuento para el email (ej: "20% de descuento").
     *
     * @param string|null $type  'percentage' | 'fixed'
     * @param mixed       $value Valor numérico del descuento.
     */
    public static function formatDiscountText(?string $type, $value): string
    {
        $value = self::normalizeDecimal($value);

        return $type === 'fixed'
            ? '$'.$value.' de descuento'
            : $value.'% de descuento';
    }

    /**
     * Fecha de validez en formato legible (d/m/Y).
     *
     * @param mixed $date string Y-m-d, Carbon o null.
     */
    public static function formatValidUntil($date): string
    {
        if (empty($date)) {
            return '';
        }

        $parsed = $date instanceof \Carbon\CarbonInterface
            ? $date
            : \Carbon\Carbon::parse($date);

        return $parsed->format('d/m/Y');
    }

    /**
     * Normaliza un valor decimal para mostrarlo sin ceros innecesarios.
     */
    private static function normalizeDecimal($value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        $number = (float) $value;
        $formatted = number_format($number, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    }
}
