@php
    /** @var \App\Models\Category $record */
@endphp

<div style="margin-bottom: .5rem">
    <strong>Subcategorías existentes</strong>
</div>

@if($record->children->isEmpty())
    <div>Esta categoría aún no tiene subcategorías.</div>
@else
    <ul style="padding-left: 1rem">
        @foreach($record->children as $child)
            <li style="margin-bottom: .4rem">
                <span style="font-weight:600">{{ $child->name }}</span>
                <small style="color:#6b7280"> — {{ $child->description }}</small>
                <div style="margin-top:.2rem">
                    <a href="{{ \App\Filament\Resources\CategoryResource::getUrl('edit', ['record' => $child->id]) }}" class="filament-link">
                        Editar
                    </a>
                </div>
            </li>
        @endforeach
    </ul>
@endif
