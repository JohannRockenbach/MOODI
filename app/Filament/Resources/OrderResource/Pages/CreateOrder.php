<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Product;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    // 🔒 Propiedad pública para saber si viene del mapa (persiste en Livewire)
    public bool $isLockedFromMap = false;

    public ?int $lockedTableId = null;

    // Redirigir a la lista después de crear
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Montar el componente con datos iniciales
     */
    public function mount(): void
    {
        parent::mount();

        // Preparar datos iniciales
        $initialData = [];

        // Auto-completar mesa desde query string
        if (request()->has('table_id')) {
            $tableId = (int) request()->get('table_id');
            if ($tableId > 0) {
                // 🔒 Marcar que viene del mapa (esta propiedad persiste en Livewire)
                $this->isLockedFromMap = true;
                $this->lockedTableId = $tableId;

                // Usar campos con prefijo _locked_ cuando viene del mapa
                $initialData['_locked_table_id'] = $tableId;
                $initialData['_locked_type'] = 'salon';
            }
        }

        // Auto-completar mozo (usuario autenticado)
        if (Auth::check()) {
            $initialData['waiter_id'] = Auth::id();
        }

        // Estado por defecto
        if (! isset($initialData['status'])) {
            $initialData['status'] = 'pending';
        }

        // Stock no descontado por defecto en pedidos nuevos
        if (! array_key_exists('stock_deducted', $initialData)) {
            $initialData['stock_deducted'] = false;
        }

        // Llenar el formulario con los datos
        if (! empty($initialData)) {
            $this->form->fill($initialData);
        }
    }

    /**
     * Mutate data antes de guardar
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // SIEMPRE asegurar restaurant_id
        $data['restaurant_id'] = 1;

        // 🔒 PROTECCIÓN: Mapear campos bloqueados (_locked_*) a campos reales
        if (isset($data['_locked_type'])) {
            $data['type'] = $data['_locked_type'];
            unset($data['_locked_type']); // Limpiar el campo temporal
            Log::info("🔒 Mapeando _locked_type -> type: {$data['type']}");
        }

        if (isset($data['_locked_table_id'])) {
            $data['table_id'] = $data['_locked_table_id'];
            unset($data['_locked_table_id']); // Limpiar el campo temporal
            Log::info("🔒 Mapeando _locked_table_id -> table_id: {$data['table_id']}");
        }

        // 🔒 DOBLE PROTECCIÓN: Si viene del mapa en la URL, FORZAR valores
        if (request()->has('table_id')) {
            $tableIdFromUrl = (int) request()->get('table_id');
            $data['type'] = 'salon';
            $data['table_id'] = $tableIdFromUrl;
            Log::info("🔒🔒 FORZADO desde URL: type=salon, table_id={$tableIdFromUrl}");
        }

        // Auto-asignar mozo si no está presente
        if (Auth::check() && empty($data['waiter_id'])) {
            $data['waiter_id'] = Auth::id();
        }

        // Estado por defecto
        if (empty($data['status'])) {
            $data['status'] = 'pending';
        }

        // Hotfix: nunca persistir null en stock_deducted
        $data['stock_deducted'] = (bool) ($data['stock_deducted'] ?? false);

        return $data;
    }

    /**
     * Validar stock ANTES de crear el pedido
     */
    protected function beforeCreate(): void
    {
        Log::info('--- CreateOrder::beforeCreate() - Validando stock ---');

        $orderProducts = $this->data['orderProducts'] ?? [];

        if (empty($orderProducts)) {
            Log::info('No hay productos para validar');

            return;
        }

        // Validar stock de cada producto
        foreach ($orderProducts as $index => $item) {
            $productId = $item['product_id'] ?? null;
            $quantity = $item['quantity'] ?? 0;

            if (! $productId) {
                continue;
            }

            // Obtener el producto con su stock real
            $product = Product::find($productId);

            if (! $product) {
                Notification::make()
                    ->danger()
                    ->title('Error de validación')
                    ->body("Producto no encontrado (ID: {$productId}).")
                    ->persistent()
                    ->send();

                $this->halt();

                return;
            }

            // VALIDACIÓN DE STOCK
            if ($quantity > $product->real_stock) {
                Log::warning("❌ Stock insuficiente: {$product->name}. Solicitado: {$quantity}, Disponible: {$product->real_stock}");

                Notification::make()
                    ->danger()
                    ->title('Stock Insuficiente')
                    ->body("No hay stock suficiente para: **{$product->name}**.\n\n📦 Solicitado: **{$quantity}**\n✅ Disponible: **{$product->real_stock}**")
                    ->persistent()
                    ->send();

                // Detener la creación del pedido
                $this->halt();

                return;
            }

            Log::info("✅ Stock OK: {$product->name}. Solicitado: {$quantity}, Disponible: {$product->real_stock}");
        }

        Log::info('✅ Validación de stock completada. Todos los productos tienen stock suficiente.');
    }

    /**
     * Marcar la mesa como OCUPADA al crear un pedido de salón.
     *
     * La máquina de estados está centralizada en Table::occupy(): solo avanza
     * desde 'available'/'reserved' y nunca pisa una mesa ya ocupada o en
     * mantenimiento.
     */
    protected function afterCreate(): void
    {
        $record = $this->record;

        // Solo pedidos de salón con mesa asignada ocupan una mesa.
        if (! $record || $record->type !== 'salon' || ! $record->table_id) {
            return;
        }

        \App\Models\Table::find($record->table_id)?->occupy();
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
}
