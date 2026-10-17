@php
    /** @var \App\Models\Sale $sale */
    $ars = static fn (float|int|string|null $value): string => '$ '.number_format((float) ($value ?? 0), 2, ',', '.');

    $paymentLabels = [
        'cash' => 'Efectivo',
        'card' => 'Tarjeta',
        'transfer' => 'Transferencia',
        'qr' => 'QR',
    ];

    $statusLabels = [
        'paid' => 'Pagada',
        'pending' => 'Pendiente',
        'annulled' => 'Anulada',
        'failed' => 'Fallida',
    ];

    $orderUrl = $sale->order_id
        ? \App\Filament\Resources\OrderResource::getUrl('edit', ['record' => $sale->order_id])
        : null;

    $contactOrigin = (string) ($payload['contact_origin'] ?? '-');
    $channelLabel = mb_strtolower($contactOrigin) === 'pedido web/local'
        ? 'Web/Local'
        : $contactOrigin;
@endphp

<div class="space-y-3" role="region" aria-label="Resumen de venta">
    <section aria-labelledby="venta-ejecutivo" class="space-y-2">
        <h2 id="venta-ejecutivo" class="sr-only">Resumen ejecutivo</h2>

        <div class="grid grid-cols-2 gap-2 md:grid-cols-4">
            <article class="rounded-lg bg-gray-50 px-2.5 py-2 ring-1 ring-gray-200 dark:bg-gray-900/70 dark:ring-gray-700">
                <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Ticket</p>
                <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">#{{ $sale->id }}</p>
            </article>

            <article class="rounded-lg bg-gray-50 px-2.5 py-2 ring-1 ring-gray-200 dark:bg-gray-900/70 dark:ring-gray-700">
                <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Fecha</p>
                <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ optional($sale->created_at)->format('d/m/Y H:i') ?? '-' }}</p>
            </article>

            <article class="rounded-lg bg-gray-50 px-2.5 py-2 ring-1 ring-gray-200 dark:bg-gray-900/70 dark:ring-gray-700">
                <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Estado</p>
                <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $statusLabels[$sale->status] ?? ucfirst((string) $sale->status) }}</p>
            </article>

            <article class="rounded-lg bg-emerald-50 px-2.5 py-2 ring-1 ring-emerald-200 dark:bg-emerald-950/40 dark:ring-emerald-900/70">
                <p class="text-[11px] font-medium uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Total</p>
                <p class="mt-1 text-sm font-semibold text-emerald-700 dark:text-emerald-300">{{ $ars($sale->total_amount) }}</p>
            </article>
        </div>
    </section>

    <div class="grid grid-cols-1 gap-2.5 lg:grid-cols-2">
        <section aria-labelledby="venta-operativa" class="rounded-lg bg-white px-3 py-2.5 ring-1 ring-gray-200 dark:bg-gray-900/50 dark:ring-gray-700">
            <h3 id="venta-operativa" class="text-sm font-semibold text-gray-900 dark:text-gray-100">Datos operativos</h3>

            <dl class="mt-2.5 grid grid-cols-1 gap-x-3 gap-y-1.5 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs text-gray-500">Pago</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $paymentLabels[$sale->payment_method] ?? ucfirst((string) $sale->payment_method) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Caja</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $sale->caja_id ? 'Caja #'.$sale->caja_id : 'Sin caja' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Pedido</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100">
                        @if ($orderUrl)
                            <a href="{{ $orderUrl }}" target="_blank" class="text-primary-600 hover:underline dark:text-primary-400">#{{ $sale->order_id }}</a>
                        @else
                            Sin pedido
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Canal del pedido</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $channelLabel }}</dd>
                </div>
            </dl>
        </section>

        <section aria-labelledby="venta-contacto" class="rounded-lg bg-white px-3 py-2.5 ring-1 ring-gray-200 dark:bg-gray-900/50 dark:ring-gray-700">
            <h3 id="venta-contacto" class="text-sm font-semibold text-gray-900 dark:text-gray-100">Datos del cliente y entrega</h3>

            <dl class="mt-2.5 grid grid-cols-1 gap-x-3 gap-y-1.5 text-sm sm:grid-cols-2">
            @if (($payload['is_web_order'] ?? false) === true)
                <div>
                    <dt class="text-xs text-gray-500">Cliente</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $payload['web_customer'] ?? 'No informado' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Teléfono</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $payload['web_phone'] ?? 'No informado' }}</dd>
                </div>

                @if (($payload['is_delivery_order'] ?? false) === true)
                    <div class="sm:col-span-2">
                        <dt class="text-xs text-gray-500">Dirección de entrega</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $payload['web_address'] ?? 'No informada' }}</dd>
                    </div>
                @endif
            @else
                <div class="sm:col-span-2">
                    <dt class="text-xs text-gray-500">Mozo</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100">{{ data_get($sale, 'order.waiter.name', 'Sin mozo asignado') }}</dd>
                </div>
            @endif
            </dl>
        </section>
    </div>

    <section aria-labelledby="venta-importes" class="rounded-lg bg-white px-3 py-2.5 ring-1 ring-gray-200 dark:bg-gray-900/50 dark:ring-gray-700">
        <h3 id="venta-importes" class="text-sm font-semibold text-gray-900 dark:text-gray-100">Resumen de importes</h3>

        <dl class="mt-2.5 grid grid-cols-1 gap-x-3 gap-y-1.5 text-sm sm:grid-cols-3">
            <div>
                <dt class="text-xs text-gray-500">Subtotal</dt>
                <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $ars($payload['financial_subtotal'] ?? 0) }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500">Descuento</dt>
                <dd class="font-medium text-amber-700 dark:text-amber-300">- {{ $ars($payload['financial_discount'] ?? 0) }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500">Total final</dt>
                <dd class="font-semibold text-emerald-700 dark:text-emerald-300">{{ $ars($sale->total_amount) }}</dd>
            </div>
        </dl>
    </section>

    <section class="space-y-2" aria-label="Detalles expandibles">
        <details class="group rounded-lg bg-white px-3 py-2.5 ring-1 ring-gray-200 dark:bg-gray-900/50 dark:ring-gray-700">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-2 text-sm font-semibold text-gray-900 marker:hidden dark:text-gray-100">
                <span>Ítems del pedido</span>
                <span class="text-xs font-medium text-gray-500 transition group-open:rotate-180">⌄</span>
            </summary>

            @if (($payload['has_order_items'] ?? false) === true)
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left text-xs text-gray-500 dark:border-gray-700">
                                <th class="py-2 pr-4">Producto</th>
                                <th class="py-2 pr-4 text-right">Cant.</th>
                                <th class="py-2 pr-4 text-right">Precio</th>
                                <th class="py-2 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (($payload['order_items'] ?? []) as $item)
                                <tr class="border-b border-gray-100/90 last:border-0 dark:border-gray-800/80">
                                    <td class="py-2 pr-4 text-gray-900 dark:text-gray-100">{{ $item['product'] ?? '-' }}</td>
                                    <td class="py-2 pr-4 text-right text-gray-700 dark:text-gray-200">{{ $item['quantity'] ?? '-' }}</td>
                                    <td class="py-2 pr-4 text-right text-gray-700 dark:text-gray-200">{{ $ars($item['price'] ?? 0) }}</td>
                                    <td class="py-2 text-right font-semibold text-gray-900 dark:text-gray-100">{{ $ars($item['subtotal'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="mt-3 text-sm text-gray-500">Sin ítems registrados para este pedido.</p>
            @endif
        </details>

        <details class="group rounded-lg bg-white px-3 py-2.5 ring-1 ring-gray-200 dark:bg-gray-900/50 dark:ring-gray-700">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-2 text-sm font-semibold text-gray-900 marker:hidden dark:text-gray-100">
                <span>Descuentos aplicados</span>
                <span class="text-xs font-medium text-gray-500 transition group-open:rotate-180">⌄</span>
            </summary>

            @if (($payload['has_discounts'] ?? false) === true)
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left text-xs text-gray-500 dark:border-gray-700">
                                <th class="py-2 pr-4">Cupón</th>
                                <th class="py-2 pr-4">Tipo / valor</th>
                                <th class="py-2 text-right">Impacto aplicado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (($payload['discount_lines'] ?? []) as $line)
                                <tr class="border-b border-gray-100/90 last:border-0 dark:border-gray-800/80">
                                    <td class="py-2 pr-4 text-gray-900 dark:text-gray-100">{{ $line['code'] ?? '-' }}</td>
                                    <td class="py-2 pr-4 text-gray-700 dark:text-gray-200">{{ $line['type_value'] ?? '-' }}</td>
                                    <td class="py-2 text-right font-semibold text-amber-700 dark:text-amber-300">- {{ $ars($line['impact'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="mt-3 text-sm text-gray-500">Sin cupón aplicado.</p>
            @endif
        </details>
    </section>
</div>
