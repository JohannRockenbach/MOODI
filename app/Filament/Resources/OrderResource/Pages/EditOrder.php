<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;
    
    // 🔒 Propiedades para detectar si se abrió desde el mapa
    public bool $isLockedFromMap = false;
    public ?int $lockedTableId = null;
    
    //  Redirigir a la lista después de editar
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
    
    /**
     * Montar el componente - detectar si viene del mapa
     */
    public function mount(int | string $record): void
    {
        parent::mount($record);
        
        // Detectar si viene del mapa con parámetro ?from_map=1
        if (request()->has('from_map')) {
            $this->isLockedFromMap = true;
            // La mesa actual del pedido está en $this->record
            $this->lockedTableId = $this->record->table_id ?? null;
        }
    }
    
    /**
     * Método público para que el formulario verifique si está bloqueado
     */
    public function isFromTableMap(): bool
    {
        return $this->isLockedFromMap;
    }
    
    /**
     * Obtener el ID de la mesa bloqueada
     */
    public function getLockedTableId(): ?int
    {
        return $this->lockedTableId;
    }

    /**
     * Validar antes de guardar que no se retroceda de estados completados
     */
    protected function beforeSave(): void
    {
        $order = $this->getRecord();
        $originalStatus = $order->getOriginal('status');
        $newStatus = $this->data['status'];

        // Validar que no se pueda retroceder de completed
        if ($originalStatus === 'completed' && in_array($newStatus, ['pending', 'processing'])) {
            \Filament\Notifications\Notification::make()
                ->danger()
                ->title('Acción no permitida')
                ->body('No se puede cambiar el estado de un pedido completado.')
                ->persistent()
                ->send();
            
            // Detener el guardado lanzando una excepción
            $this->halt();
        }
        
        // 🔒 Proteger campos bloqueados si vino del mapa
        if ($this->isLockedFromMap) {
            // Forzar valores originales para type y table_id
            if (isset($this->data['type']) && $this->data['type'] !== $order->getOriginal('type')) {
                $this->data['type'] = $order->getOriginal('type');
                Log::warning("🔒 EDIT: Intento de cambiar 'type' bloqueado desde mapa");
            }
            
            if (isset($this->data['table_id']) && $this->data['table_id'] !== $order->getOriginal('table_id')) {
                $this->data['table_id'] = $order->getOriginal('table_id');
                Log::warning("🔒 EDIT: Intento de cambiar 'table_id' bloqueado desde mapa");
            }
        }
    }
}
