@php
// This view is used by a Filament Table ViewColumn and receives a $record variable.
// It mounts a Livewire component that provides nested expansion and inline actions.
@endphp

<div>
    @if (isset($record) && $record)
        <livewire:category-products-panel :category-id="$record->id" />
    @else
        <div>No hay datos de categoría.</div>
    @endif
</div>
