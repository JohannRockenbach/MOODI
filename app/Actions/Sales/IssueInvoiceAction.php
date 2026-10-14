<?php

namespace App\Actions\Sales;

use App\Models\Invoice;
use App\Models\Sale;
use App\Models\User;
use App\Services\AuditService;
use App\Services\InvoiceNumberService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Emisión de comprobante de venta básico (CU-001.12).
 *
 * Crea un Invoice con número correlativo (NO CAE), datos del cliente (si el
 * pedido tiene Cliente) y el vínculo a la Sale. Solo super_admin (decisión
 * documentada: consistente con annul/restore/update, todas super_admin only).
 */
class IssueInvoiceAction
{
    public function __invoke(Sale $sale, User $user): Invoice
    {
        // Autorización primaria: 403 (AuthorizationException) si no es super_admin.
        Gate::forUser($user)->authorize('emitInvoice', $sale);

        if ($sale->status !== 'paid' || $sale->isAnnulled()) {
            throw new \DomainException('La venta no está pagada: no puede emitirse un comprobante.');
        }

        if ($sale->invoice()->exists()) {
            throw new \DomainException('La venta ya tiene un comprobante emitido.');
        }

        $restaurantId = (int) $sale->restaurant_id;
        $customerData = $this->resolveCustomerData($sale);

        $invoice = DB::transaction(function () use ($sale, $restaurantId, $customerData): Invoice {
            // El número se calcula DENTRO de la transacción: el lockForUpdate
            // de InvoiceNumberService se mantiene hasta el commit del insert.
            $number = InvoiceNumberService::nextNumber($restaurantId);

            return Invoice::create([
                'cae_number' => $number,
                'customer_data' => $customerData,
                'sale_id' => $sale->id,
            ]);
        });

        AuditService::record('invoice.issued', $invoice, [
            'sale_id' => $sale->id,
            'number' => $invoice->cae_number,
        ], $user);

        return $invoice;
    }

    private function resolveCustomerData(Sale $sale): array
    {
        $customer = $sale->order?->customer;

        if (! $customer) {
            return [];
        }

        return [
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
        ];
    }
}
