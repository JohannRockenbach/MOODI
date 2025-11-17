@php
    /** @var \App\Models\Caja|null $record */
@endphp

@if(isset($record) && $record)
    @livewire('caja-balance', ['cajaId' => $record->id])
@else
    <div>-</div>
@endif
