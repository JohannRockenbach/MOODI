<?php

namespace App\Console\Commands;

use App\Filament\Pages\SendCampaign;
use App\Models\Cliente;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;

class CheckLoyaltyPromo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loyalty:check-promo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detecta oportunidades de fidelización: Cumpleaños y Clientes VIP';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('👑 Sistema de Fidelización - Analizando clientes...');
        $this->line('');

        $admins = User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['super_admin', 'administrador']);
        })->get();

        if ($admins->isEmpty()) {
            $this->warn('⚠️  No se encontraron administradores para notificar.');
            return Command::SUCCESS;
        }

        // ========================================
        // Estrategia A: Cumpleaños 🎂
        // ========================================
        $this->info('🎂 Estrategia A: Detectando cumpleaños del día...');
        
        $birthdayClients = Cliente::whereMonth('birthday', now()->month)
            ->whereDay('birthday', now()->day)
            ->get();

        if ($birthdayClients->isEmpty()) {
            $this->comment('   ℹ️  No hay cumpleaños hoy.');
        } else {
            $this->info("   ✅ Se encontraron {$birthdayClients->count()} cumpleañero(s) hoy:");
            
            foreach ($birthdayClients as $client) {
                $this->line("      → {$client->name} ({$client->email})");
                
                // Calcular edad si es posible
                $age = $client->birthday ? now()->diffInYears($client->birthday) : null;
                $ageText = $age ? " ¡Cumple {$age} años!" : '';
                
                $title = "🎂 ¡Cumpleaños de {$client->name}!";
                $body = "Hoy es el cumpleaños de **{$client->name}**.{$ageText}\n\n"
                    . "💡 **Sugerencia de Marketing**: Enviar un regalo especial como un **postre gratis** "
                    . "o un **descuento del 15%** en su próximo pedido.\n\n"
                    . "🎁 Esta estrategia aumenta la retención y genera lealtad emocional.";

                // URL de campaña con datos pre-llenados
                $campaignUrl = SendCampaign::getUrl([
                    'subject' => $title,
                    'body' => $body . "\n\n---\n\n"
                        . "**Estimado/a {$client->name}**,\n\n"
                        . "🎉 ¡Feliz Cumpleaños! 🎉\n\n"
                        . "En este día especial, queremos regalarte un **postre gratis** en tu próxima visita.\n\n"
                        . "Simplemente menciona este mensaje al hacer tu pedido.\n\n"
                        . "¡Que tengas un día increíble! 🎂🎈",
                    'testEmail' => $client->email ?? '',
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
                        
                        Action::make('view_client')
                            ->label('Ver Cliente')
                            ->icon('heroicon-o-user')
                            ->color('gray')
                            ->url("/admin/clientes/{$client->id}/edit")
                            ->openUrlInNewTab(true),
                    ])
                    ->sendToDatabase($admins);

                $this->info("      ✉️  Notificación enviada a administradores");
            }
        }

        $this->line('');

        // ========================================
        // Estrategia B: Clientes VIP 👑
        // ========================================
        $this->info('👑 Estrategia B: Detectando clientes VIP (5+ pedidos en 30 días)...');
        
        $vipClients = Cliente::whereHas('orders', function ($query) {
            $query->where('created_at', '>=', now()->subDays(30));
        }, '>=', 5)
        ->withCount([
            'orders' => fn($q) => $q->where('created_at', '>=', now()->subDays(30))
        ])
        ->get();

        if ($vipClients->isEmpty()) {
            $this->comment('   ℹ️  No se detectaron nuevos clientes VIP este mes.');
        } else {
            $this->info("   ✅ Se encontraron {$vipClients->count()} cliente(s) VIP:");
            
            foreach ($vipClients as $client) {
                $ordersCount = $client->orders_count;
                $this->line("      → {$client->name} ({$ordersCount} pedidos este mes)");
                
                $title = "👑 Nuevo Cliente VIP: {$client->name}";
                $body = "**{$client->name}** ha realizado **{$ordersCount} pedidos** en los últimos 30 días.\n\n"
                    . "💡 **Sugerencia de Marketing**: Enviar un **cupón de fidelidad del 20%** "
                    . "o beneficios exclusivos para clientes frecuentes.\n\n"
                    . "👑 Los clientes VIP generan el 80% de los ingresos recurrentes. "
                    . "¡Es momento de recompensarlos!";

                // URL de campaña con datos pre-llenados
                $campaignUrl = SendCampaign::getUrl([
                    'subject' => $title,
                    'body' => $body . "\n\n---\n\n"
                        . "**Estimado/a {$client->name}**,\n\n"
                        . "🌟 ¡Eres un Cliente VIP! 🌟\n\n"
                        . "Hemos notado que nos visitas frecuentemente y queremos agradecértelo.\n\n"
                        . "🎁 **Regalo especial**: 20% de descuento en tu próximo pedido con el código **VIP20**.\n\n"
                        . "Además, a partir de ahora tendrás:\n"
                        . "• Prioridad en la cocina\n"
                        . "• Postre gratis en pedidos grandes\n"
                        . "• Acceso a promociones exclusivas\n\n"
                        . "¡Gracias por tu lealtad! 👑",
                    'testEmail' => $client->email ?? '',
                ]);

                Notification::make()
                    ->title($title)
                    ->body($body)
                    ->icon('heroicon-o-star')
                    ->iconColor('warning')
                    ->actions([
                        Action::make('create_campaign')
                            ->label('Crear Campaña')
                            ->icon('heroicon-o-megaphone')
                            ->color('warning')
                            ->button()
                            ->url($campaignUrl),
                        
                        Action::make('view_client')
                            ->label('Ver Cliente')
                            ->icon('heroicon-o-user')
                            ->color('gray')
                            ->url("/admin/clientes/{$client->id}/edit")
                            ->openUrlInNewTab(true),
                        
                        Action::make('view_orders')
                            ->label('Ver Pedidos')
                            ->icon('heroicon-o-shopping-bag')
                            ->color('gray')
                            ->url("/admin/orders")
                            ->openUrlInNewTab(true),
                    ])
                    ->sendToDatabase($admins);

                $this->info("      ✉️  Notificación enviada a administradores");
            }
        }

        $this->line('');
        $this->info('✅ Sistema de Fidelización completado.');
        
        $totalNotifications = $birthdayClients->count() + $vipClients->count();
        $this->comment("📊 Resumen: {$birthdayClients->count()} cumpleaños + {$vipClients->count()} VIP = {$totalNotifications} oportunidades de fidelización");

        return Command::SUCCESS;
    }
}
