@php
/** @var \App\Models\Caja $record */
$lines = [];
$total = 0;
foreach ($record->sales as $sale) {
    if ($sale->order && $sale->order->products->count()) {
        foreach ($sale->order->products as $product) {
            $qty = $product->pivot->quantity ?? 1;
            $unit = $product->price ?? 0;
            $lineTotal = $unit * $qty;
            $total += $lineTotal;
            $lines[] = [
                'name' => $product->name,
                'qty' => $qty,
                'subtotal' => number_format($lineTotal, 2),
            ];
        }
    } else {
        // Venta sin order/productos: renderizar la venta simple
        $total += $sale->total_amount;
        $lines[] = [
            'name' => 'Venta directa',
            'qty' => 1,
            'subtotal' => number_format($sale->total_amount, 2),
        ];
    }
}
@endphp

<div style="max-height:300px; overflow:auto;">
<table style="width:100%; border-collapse:collapse;">
    <thead>
        <tr>
            <th style="text-align:left; padding:6px; border-bottom:1px solid #e5e7eb;">Producto</th>
            <th style="text-align:center; padding:6px; border-bottom:1px solid #e5e7eb;">Cant.</th>
            <th style="text-align:right; padding:6px; border-bottom:1px solid #e5e7eb;">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($lines as $line)
            <tr>
                <td style="padding:6px; border-bottom:1px solid #f3f4f6;">{{ $line['name'] }}</td>
                <td style="text-align:center; padding:6px; border-bottom:1px solid #f3f4f6;">{{ $line['qty'] }}</td>
                <td style="text-align:right; padding:6px; border-bottom:1px solid #f3f4f6;">{{ $line['subtotal'] }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="2" style="text-align:right; padding:6px; font-weight:600;">TOTAL</td>
            <td style="text-align:right; padding:6px; font-weight:600;">{{ number_format($total, 2) }}</td>
        </tr>
    </tbody>
</table>
</div>
