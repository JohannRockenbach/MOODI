<div>
    @livewire('order-status-selector', ['orderId' => $getRecord()->id, 'status' => $getState()], key($getRecord()->id))
</div>
