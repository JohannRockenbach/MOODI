@php
    $items = $sale->orderItems();
    $discountTotal = (float) $sale->discountTotal();
    $subtotal = (float) $sale->subtotalBeforeDiscount();
    $total = (float) $sale->total_amount;

    $customerData = is_array($invoice->customer_data) ? $invoice->customer_data : [];
    $customerName = $customerData['name'] ?? $sale->order?->customer?->name ?? $sale->order?->customer_name ?? null;

    $paymentLabel = match ($sale->payment_method) {
        'cash' => 'Efectivo',
        'card' => 'Tarjeta',
        'transfer' => 'Transferencia',
        'qr' => 'QR',
        default => ucfirst((string) $sale->payment_method),
    };

    $restaurantName = $sale->restaurant?->name ?? ('Restaurante #'.($sale->restaurant_id ?? 1));
@endphp

<div style="font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; background:#0b0e15; border:1px solid #272a31; border-radius:1rem; overflow:hidden;">
    <div style="background:#1c2026; padding:1.25rem 1.5rem; border-bottom:1px solid #272a31; text-align:center;">
        <div style="font-size:0.78rem; letter-spacing:0.18em; text-transform:uppercase; color:#c1c6d5;">{{ $restaurantName }}</div>
        <div style="margin-top:0.4rem; font-size:1.35rem; font-weight:900; letter-spacing:0.05em; color:#fbbc48; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;">{{ $invoice->cae_number }}</div>
        <div style="margin-top:0.35rem; font-size:0.78rem; letter-spacing:0.12em; text-transform:uppercase; color:#c1c6d5;">Comprobante de venta</div>
    </div>

    <div style="padding:1.25rem 1.5rem;">
        <dl style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem 1.25rem; margin:0; font-size:0.85rem;">
            <div>
                <dt style="color:#c1c6d5; text-transform:uppercase; font-size:0.7rem; letter-spacing:0.08em;">Fecha</dt>
                <dd style="margin:0; color:#e0e2ec;">{{ optional($invoice->created_at)->format('d/m/Y H:i') ?? '-' }}</dd>
            </div>
            <div>
                <dt style="color:#c1c6d5; text-transform:uppercase; font-size:0.7rem; letter-spacing:0.08em;">Venta</dt>
                <dd style="margin:0; color:#e0e2ec;">#{{ $sale->id }}</dd>
            </div>
            <div>
                <dt style="color:#c1c6d5; text-transform:uppercase; font-size:0.7rem; letter-spacing:0.08em;">Caja</dt>
                <dd style="margin:0; color:#e0e2ec;">{{ $sale->caja_id ? 'Caja #'.$sale->caja_id : 'Sin caja' }}</dd>
            </div>
            <div>
                <dt style="color:#c1c6d5; text-transform:uppercase; font-size:0.7rem; letter-spacing:0.08em;">Método de pago</dt>
                <dd style="margin:0; color:#e0e2ec;">{{ $paymentLabel }}</dd>
            </div>

            @if ($customerName)
                <div style="grid-column:1 / -1;">
                    <dt style="color:#c1c6d5; text-transform:uppercase; font-size:0.7rem; letter-spacing:0.08em;">Cliente</dt>
                    <dd style="margin:0; color:#e0e2ec;">{{ $customerName }}</dd>
                </div>
            @endif
        </dl>

        <table style="width:100%; margin-top:1.1rem; border-collapse:collapse; font-size:0.85rem;">
            <thead>
                <tr style="border-bottom:1px solid #272a31; color:#c1c6d5; text-transform:uppercase; font-size:0.7rem; letter-spacing:0.06em;">
                    <th style="text-align:left; padding:0.5rem 0;">Producto</th>
                    <th style="text-align:right; padding:0.5rem 0;">Cant.</th>
                    <th style="text-align:right; padding:0.5rem 0;">Precio</th>
                    <th style="text-align:right; padding:0.5rem 0;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr style="border-bottom:1px solid #272a31;">
                        <td style="padding:0.5rem 0; color:#e0e2ec;">{{ $item['product'] ?? '-' }}</td>
                        <td style="text-align:right; padding:0.5rem 0; color:#c1c6d5;">{{ $item['quantity'] ?? '-' }}</td>
                        <td style="text-align:right; padding:0.5rem 0; color:#c1c6d5;">${{ number_format((float) ($item['price'] ?? 0), 2, ',', '.') }}</td>
                        <td style="text-align:right; padding:0.5rem 0; color:#e0e2ec;">${{ number_format((float) ($item['subtotal'] ?? 0), 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="padding:0.75rem 0; color:#c1c6d5;">Sin ítems registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div style="margin-top:1rem; border-top:1px solid #272a31; padding-top:0.75rem; font-size:0.9rem;">
            <div style="display:flex; justify-content:space-between; color:#c1c6d5;">
                <span>Subtotal</span>
                <span>${{ number_format($subtotal, 2, ',', '.') }}</span>
            </div>

            @if ($discountTotal > 0)
                <div style="display:flex; justify-content:space-between; color:#c1c6d5; margin-top:0.35rem;">
                    <span>Descuento</span>
                    <span>-${{ number_format($discountTotal, 2, ',', '.') }}</span>
                </div>
            @endif

            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:0.6rem; padding-top:0.6rem; border-top:1px solid #272a31;">
                <span style="font-weight:700; color:#e0e2ec;">TOTAL</span>
                <span style="font-size:1.3rem; font-weight:900; color:#fbbc48;">${{ number_format($total, 2, ',', '.') }}</span>
            </div>
        </div>

        <div style="margin-top:1rem; text-align:center; color:#c1c6d5; font-size:0.72rem; line-height:1.5;">
            Comprobante básico — sin validez fiscal (no es factura electrónica).
        </div>
    </div>
</div>
