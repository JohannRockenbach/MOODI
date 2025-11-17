<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Product;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;
    
    // Redirigir a la lista después de crear
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
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

            if (!$productId) {
                continue;
            }

            // Obtener el producto con su stock real
            $product = Product::find($productId);

            if (!$product) {
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
}
