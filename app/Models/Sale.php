<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Sale extends Model
{
    use HasFactory;

    protected ?Collection $cachedOrderItems = null;

    protected ?Collection $cachedDiscounts = null;

    protected ?float $cachedDiscountTotal = null;

    protected ?array $cachedViewModalPayload = null;

    protected $fillable = [
        'total_amount',
        'payment_method',
        'status',
        'order_id',
        'cashier_id',
        'restaurant_id',
        'caja_id',
        'annulled_at',
        'annulled_by',
        'annulled_reason',
        'previous_status',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'annulled_at' => 'datetime',
    ];

    // --- Relaciones ---

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Desglose de pagos del SPLIT: una fila (método + monto) por método usado.
     * Sin split hay UNA fila con el método único y el total.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    public function annulledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'annulled_by');
    }

    public function discounts(): BelongsToMany
    {
        return $this->belongsToMany(Discount::class)
            ->withPivot('amount_discounted');
    }

    public function annul(User $user, string $reason): self
    {
        if ($this->status === 'annulled' || ! is_null($this->annulled_at)) {
            throw new \DomainException('La venta ya fue anulada.');
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw new \InvalidArgumentException('El motivo de anulación es obligatorio.');
        }

        return DB::transaction(function () use ($user, $reason): self {
            /** @var self $lockedSale */
            $lockedSale = self::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSale->status === 'annulled' || ! is_null($lockedSale->annulled_at)) {
                throw new \DomainException('La venta ya fue anulada.');
            }

            $lockedSale->forceFill([
                'previous_status' => $lockedSale->status,
                'status' => 'annulled',
                'annulled_at' => now(),
                'annulled_by' => $user->getKey(),
                'annulled_reason' => $reason,
            ]);

            $lockedSale->save();

            $this->setRawAttributes($lockedSale->getAttributes(), true);
            $this->setRelations($lockedSale->getRelations());

            return $this;
        });
    }

    public function restoreAnnulment(): self
    {
        if (! $this->isAnnulled()) {
            throw new \DomainException('La venta no está anulada.');
        }

        return DB::transaction(function (): self {
            /** @var self $lockedSale */
            $lockedSale = self::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedSale->isAnnulled()) {
                throw new \DomainException('La venta no está anulada.');
            }

            $lockedSale->forceFill([
                'status' => $lockedSale->previous_status ?? 'paid',
                'annulled_at' => null,
                'annulled_by' => null,
                'annulled_reason' => null,
            ]);

            $lockedSale->save();

            $this->setRawAttributes($lockedSale->getAttributes(), true);
            $this->setRelations($lockedSale->getRelations());

            return $this;
        });
    }

    public function isAnnulled(): bool
    {
        return $this->status === 'annulled' && ! is_null($this->annulled_at);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function orderItems(): Collection
    {
        if ($this->cachedOrderItems instanceof Collection) {
            return $this->cachedOrderItems;
        }

        $order = $this->order;

        if (! $order) {
            return $this->cachedOrderItems = collect();
        }

        $orderProducts = $order->relationLoaded('orderProducts')
            ? $order->orderProducts
            : $order->orderProducts()->with('product:id,name')->get(['id', 'order_id', 'product_id', 'quantity', 'price']);

        if ($orderProducts->isNotEmpty()) {
            $orderProducts->loadMissing('product:id,name');
        }

        return $this->cachedOrderItems = $orderProducts
            ->map(function ($item): array {
                $quantity = (float) $item->quantity;
                $price = (float) $item->price;

                return [
                    'product' => $item->product?->name ?? 'Producto eliminado',
                    'quantity' => rtrim(rtrim(number_format($quantity, 2, ',', '.'), '0'), ','),
                    'price' => $price,
                    'subtotal' => $quantity * $price,
                ];
            })
            ->values();
    }

    public function hasOrderItems(): bool
    {
        return $this->orderItems()->isNotEmpty();
    }

    public function discountTotal(): float
    {
        if (is_float($this->cachedDiscountTotal)) {
            return $this->cachedDiscountTotal;
        }

        $discounts = $this->resolvedDiscounts();

        return $this->cachedDiscountTotal = (float) $discounts
            ->sum(fn ($discount): float => (float) ($discount->pivot->amount_discounted ?? 0));
    }

    public function subtotalBeforeDiscount(): float
    {
        return (float) $this->total_amount + $this->discountTotal();
    }

    public function hasDiscounts(): bool
    {
        return $this->discountTotal() > 0;
    }

    public function resolvedDiscounts(): Collection
    {
        if ($this->cachedDiscounts instanceof Collection) {
            return $this->cachedDiscounts;
        }

        return $this->cachedDiscounts = $this->relationLoaded('discounts')
            ? $this->discounts
            : $this->discounts()->get(['discounts.id', 'discounts.code', 'discounts.type', 'discounts.value']);
    }

    public function viewModalPayload(): array
    {
        if (is_array($this->cachedViewModalPayload)) {
            return $this->cachedViewModalPayload;
        }

        $cacheKey = $this->viewModalPayloadCacheKey();
        $request = request();

        if ($request?->attributes->has($cacheKey)) {
            $cachedPayload = $request->attributes->get($cacheKey);

            if (is_array($cachedPayload)) {
                return $this->cachedViewModalPayload = $cachedPayload;
            }
        }

        $this->loadMissing([
            'order' => fn ($query) => $query->select([
                'id',
                'type',
                'customer_name',
                'delivery_phone',
                'delivery_address',
                'waiter_id',
                'customer_id',
            ]),
            'order.waiter:id,name',
            'order.customer:id,name',
            'order.orderProducts:id,order_id,product_id,quantity,price',
            'order.orderProducts.product:id,name',
            'discounts:id,code,type,value',
        ]);

        $orderItems = $this->orderItems()->all();
        $discountTotal = $this->discountTotal();
        $hasDiscounts = $discountTotal > 0;
        $orderType = $this->order?->type;
        $isWebOrder = in_array($orderType, ['delivery', 'para_llevar'], true);

        $discountLines = $this->resolvedDiscounts()
            ->map(function ($discount): array {
                $typeLabel = match ($discount->type) {
                    'percentage' => 'Porcentaje',
                    'fixed' => 'Monto fijo',
                    default => ucfirst((string) $discount->type),
                };

                $configuredValue = $discount->type === 'percentage'
                    ? number_format((float) $discount->value, 2, ',', '.').' %'
                    : '$ '.number_format((float) $discount->value, 2, ',', '.');

                return [
                    'code' => (string) $discount->code,
                    'type_value' => $typeLabel.' · '.$configuredValue,
                    'impact' => (float) ($discount->pivot->amount_discounted ?? 0),
                ];
            })
            ->values()
            ->all();

        $payload = [
            'order_items' => $orderItems,
            'has_order_items' => ! empty($orderItems),
            'financial_subtotal' => (float) $this->total_amount + $discountTotal,
            'financial_discount' => $discountTotal,
            'has_discounts' => $hasDiscounts,
            'discount_lines' => $discountLines,
            'contact_origin' => $isWebOrder ? 'Pedido web' : 'Pedido local',
            'contact_icon' => $isWebOrder ? 'heroicon-m-globe-alt' : 'heroicon-m-home',
            'contact_color' => $isWebOrder ? 'info' : 'gray',
            'web_customer' => $this->order?->customer_name
                ?? $this->order?->customer?->name
                ?? 'No informado',
            'web_phone' => $this->order?->delivery_phone ?? 'No informado',
            'web_address' => $this->order?->delivery_address ?? 'No informada',
            'is_web_order' => $isWebOrder,
            'is_delivery_order' => $orderType === 'delivery',
        ];

        if ($request) {
            $request->attributes->set($cacheKey, $payload);
        }

        return $this->cachedViewModalPayload = $payload;
    }

    protected function viewModalPayloadCacheKey(): string
    {
        $saleKey = $this->getKey();

        return 'sale_view_modal_payload_'.($saleKey !== null ? (string) $saleKey : 'tmp_'.spl_object_id($this));
    }

    /**
     * Auto-assign the open Caja for the restaurant when creating a Sale.
     */
    protected static function booted(): void
    {
        static::creating(function ($sale) {
            // Si no se provee explícitamente, usar hora local del servidor.
            if (empty($sale->created_at)) {
                $sale->created_at = now();
            }

            // If caja_id is not already set and restaurant_id is present, try to find an open caja
            if (empty($sale->caja_id) && ! empty($sale->restaurant_id)) {
                $caja = Caja::where('restaurant_id', $sale->restaurant_id)
                    ->where('status', 'abierta')
                    ->whereDate('opening_date', now()->toDateString())
                    ->latest('opening_date')
                    ->first();

                // Fallback de seguridad para no romper flujos legacy
                if (! $caja) {
                    $caja = Caja::where('restaurant_id', $sale->restaurant_id)
                        ->where('status', 'abierta')
                        ->latest('opening_date')
                        ->first();
                }

                if ($caja) {
                    $sale->caja_id = $caja->id;
                }
            }
        });

        static::saving(function ($sale) {
            $max = 99999999.99;
            if (! is_null($sale->total_amount) && ($sale->total_amount < 0 || $sale->total_amount > $max)) {
                throw new \InvalidArgumentException('total_amount fuera de rango');
            }
        });
    }
}
