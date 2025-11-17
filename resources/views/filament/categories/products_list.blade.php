@php
    /** @var \App\Models\Category $record */
@endphp

<div style="margin-bottom: .5rem">
    <strong>Productos</strong>
</div>

@php
    $products = \App\Models\Product::where('category_id', $record->id)->get();
@endphp

@if($products->isEmpty())
    <div>No hay productos en esta categoría.</div>
@else
    <table style="width:100%; border-collapse: collapse">
        <thead>
            <tr>
                <th style="text-align:left; padding:8px">Nombre</th>
                <th style="text-align:left; padding:8px">Cantidad</th>
                <th style="text-align:left; padding:8px">Descripción / Ingredientes</th>
                <th style="text-align:left; padding:8px">Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($products as $product)
                <tr>
                    <td style="padding:8px">{{ $product->name }}</td>
                    <td style="padding:8px">{{ $product->stock ?? '-' }}</td>
                    <td style="padding:8px">{{ Str::limit($product->description, 120) }}</td>
                    <td style="padding:8px">
                        <a href="{{ \App\Filament\Resources\ProductResource::getUrl('edit', ['record' => $product->id]) }}" class="filament-link">Editar</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
