<?php

namespace App\Services;

use App\Filament\Pages\SendCampaign;
use App\Models\Cliente;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;

/**
 * Proceso de fidelización (lealtad).
 *
 * La lógica de cumpleaños vive acá para que la compartan el comando
 * `loyalty:check-promo` (chequeo diario) y el observer de `Cliente`
 * (disparo inmediato al crear un cliente que cumple años hoy).
 */
class LoyaltyPromoService
{
    /**
     * Notifica a los super_admins el cumpleaños de un cliente (Estrategia A).
     *
     * Comportamiento seguro: si el cliente no tiene birthday, si el cumpleaños
     * no cae HOY, o si no hay admins super_admin, no hace nada (no lanza
     * excepción). Esto protege a seeders/factories masivos.
     *
     * @param  Collection<int, User>|null  $admins  Admins ya resueltos (opcional)
     */
    public function notifyBirthday(Cliente $cliente, ?Collection $admins = null): void
    {
        if (! $cliente->birthday || ! $cliente->birthday->isBirthday()) {
            return;
        }

        $admins ??= User::whereHas('roles', function ($query) {
            $query->where('name', 'super_admin');
        })->get();

        if ($admins->isEmpty()) {
            return;
        }

        $title = "🎂 ¡Feliz Cumpleaños, {$cliente->name}!";
        $body = "Queremos celebrar tu día especial. Te regalamos un postre o un descuento exclusivo en tu próxima cena.\n\n🥳 ¡Festeja con nosotros!";

        // URL de campaña con datos pre-llenados
        $campaignUrl = SendCampaign::getUrl([
            'subject' => $title,
            'body' => $body,
            'discount_type' => 'percentage',
            'discount_value' => 15,
            'coupon_code' => 'CUMPLE'.strtoupper(substr($cliente->name, 0, 3)),
            'testEmail' => $cliente->email ?? '',
        ]);

        Notification::make()
            ->title($title)
            ->body($body)
            ->icon('heroicon-o-cake')
            ->iconColor('success')
            ->actions([
                Action::make('create_campaign')
                    ->label('Crear Campaña')
                    ->icon('heroicon-o-megaphone')
                    ->color('success')
                    ->button()
                    ->url($campaignUrl),
            ])
            ->sendToDatabase($admins);
    }
}