@php
    $renderStart = microtime(true);
    
    $payload = method_exists($sale, 'viewModalPayload') ? $sale->viewModalPayload() : [];

    $items = $payload['order_items'] ?? collect($sale->order?->products ?? [])->map(function ($product) {
        $quantity = (float) ($product->pivot->quantity ?? 0);
        $price = (float) ($product->pivot->price ?? 0);

        return [
            'product' => $product->name ?? 'Producto eliminado',
            'quantity' => rtrim(rtrim(number_format($quantity, 2, ',', '.'), '0'), ','),
            'price' => $price,
            'subtotal' => $quantity * $price,
        ];
    })->values()->all();

    $financialSubtotal = (float) ($payload['financial_subtotal'] ?? ((float) $sale->total_amount + (float) ($payload['financial_discount'] ?? 0)));
    $financialDiscount = (float) ($payload['financial_discount'] ?? 0);
    $totalNet = (float) $sale->total_amount;

    $statusLabel = match ($sale->status) {
        'paid' => 'Pagado',
        'pending' => 'Pendiente',
        'annulled' => 'Anulado',
        'failed' => 'Fallido',
        default => ucfirst((string) $sale->status),
    };

    $paymentLabel = match ($sale->payment_method) {
        'cash' => 'Efectivo',
        'card' => 'Tarjeta',
        'transfer' => 'Transferencia',
        default => ucfirst((string) $sale->payment_method),
    };

    $isWebOrder = (bool) ($payload['is_web_order'] ?? false);
    $isDelivery = (bool) ($payload['is_delivery_order'] ?? false);

    $responsable = $sale->order?->waiter?->name
        ?? ($isWebOrder ? ($payload['web_customer'] ?? null) : null)
        ?? $sale->cashier?->name
        ?? 'No informado';
@endphp

<div class="space-y-6 text-sm">
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900 lg:col-span-2">
            <h3 class="mb-4 text-base font-semibold text-gray-900 dark:text-gray-100">Detalle del Consumo</h3>

            @if (!empty($items))
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left">
                        <thead>
                            <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500 dark:border-white/10 dark:text-gray-400">
                                <th class="px-2 py-2">Producto</th>
                                <th class="px-2 py-2 text-right">Cantidad</th>
                                <th class="px-2 py-2 text-right">Precio unitario</th>
                                <th class="px-2 py-2 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($items as $item)
                                <tr class="border-b border-gray-100 dark:border-white/5">
                                    <td class="px-2 py-2 text-gray-800 dark:text-gray-200">{{ $item['product'] ?? '-' }}</td>
                                    <td class="px-2 py-2 text-right text-gray-700 dark:text-gray-300">{{ $item['quantity'] ?? '-' }}</td>
                                    <td class="px-2 py-2 text-right text-gray-700 dark:text-gray-300">${{ number_format((float) ($item['price'] ?? 0), 2, ',', '.') }}</td>
                                    <td class="px-2 py-2 text-right font-medium text-gray-900 dark:text-gray-100">${{ number_format((float) ($item['subtotal'] ?? 0), 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-gray-500 dark:text-gray-400">Sin ítems cargados para esta venta.</p>
            @endif
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <h3 class="mb-4 text-base font-semibold text-gray-900 dark:text-gray-100">Información de contacto</h3>

            <dl class="space-y-3">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Canal</dt>
                    <dd class="mt-1 font-medium text-gray-900 dark:text-gray-100">{{ $isWebOrder ? 'Pedido web' : 'Pedido local' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Mozo / Responsable</dt>
                    <dd class="mt-1 font-medium text-gray-900 dark:text-gray-100">{{ $responsable }}</dd>
                </div>

                @if ($isWebOrder)
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Teléfono</dt>
                        <dd class="mt-1 font-medium text-gray-900 dark:text-gray-100">{{ $payload['web_phone'] ?? 'No informado' }}</dd>
                    </div>
                @endif

                @if ($isDelivery)
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Dirección</dt>
                        <dd class="mt-1 font-medium text-gray-900 dark:text-gray-100">{{ $payload['web_address'] ?? 'No informada' }}</dd>
                    </div>
                @endif
            </dl>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900 lg:col-span-2">
            <h3 class="mb-4 text-base font-semibold text-gray-900 dark:text-gray-100">Resumen Financiero</h3>

            <dl class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2 dark:border-white/10 dark:bg-white/5">
                    <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Subtotal</dt>
                    <dd class="mt-1 font-semibold text-gray-900 dark:text-gray-100">${{ number_format($financialSubtotal, 2, ',', '.') }}</dd>
                </div>

                @if ($financialDiscount > 0)
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 dark:border-amber-500/30 dark:bg-amber-500/10">
                        <dt class="text-xs uppercase tracking-wide text-amber-700 dark:text-amber-300">Descuento</dt>
                        <dd class="mt-1 font-semibold text-amber-800 dark:text-amber-200">-${{ number_format($financialDiscount, 2, ',', '.') }}</dd>
                    </div>
                @endif

                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 dark:border-emerald-500/30 dark:bg-emerald-500/10">
                    <dt class="text-xs uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Total Neto</dt>
                    <dd class="mt-1 font-semibold text-emerald-800 dark:text-emerald-200">${{ number_format($totalNet, 2, ',', '.') }}</dd>
                </div>
            </dl>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <h3 class="mb-4 text-base font-semibold text-gray-900 dark:text-gray-100">Operativa y Cobro</h3>

            <dl class="space-y-3">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Estado</dt>
                    <dd class="mt-1 font-medium text-gray-900 dark:text-gray-100">{{ $statusLabel }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Método de pago</dt>
                    <dd class="mt-1 font-medium text-gray-900 dark:text-gray-100">{{ $paymentLabel }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Fecha</dt>
                    <dd class="mt-1 font-medium text-gray-900 dark:text-gray-100">{{ optional($sale->created_at)->format('d/m/Y H:i') ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Pedido</dt>
                    <dd class="mt-1 font-medium text-gray-900 dark:text-gray-100">{{ $sale->order?->id ? '#'.$sale->order->id : 'Sin pedido' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Caja</dt>
                    <dd class="mt-1 font-medium text-gray-900 dark:text-gray-100">{{ $sale->caja?->id ? 'Caja #'.$sale->caja->id : 'Sin caja' }}</dd>
                </div>
            </dl>
        </section>
    </div>
</div>

@php
    $durationMs = round((microtime(true) - $renderStart) * 1000, 2);
    \Illuminate\Support\Facades\Log::info('sales.view_modal.rendered', [
        'sale_id' => $sale->id,
        'duration_ms' => $durationMs,
    ]);
@endphp
