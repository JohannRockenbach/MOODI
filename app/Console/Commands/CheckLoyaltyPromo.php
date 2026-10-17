<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\User;
use App\Services\LoyaltyPromoService;
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

    public function __construct(private LoyaltyPromoService $loyaltyPromoService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('👑 Sistema de Fidelización - Analizando clientes...');
        $this->line('');

        $admins = User::whereHas('roles', function ($query) {
            $query->where('name', 'super_admin');
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

                // La notificación vive en el servicio (lo comparte el observer
                // de Cliente para el disparo inmediato al crear un cliente).
                $this->loyaltyPromoService->notifyBirthday($client, $admins);

                $this->info("      ✉️  Notificación enviada");
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

                $title = "👑 ¡Eres uno de nuestros mejores clientes!";
                $body = "Gracias por elegirnos siempre. Como agradecimiento, aquí tienes un beneficio exclusivo para tu próxima visita.";

                // URL de campaña con datos pre-llenados
                $campaignUrl = SendCampaign::getUrl([
                    'subject' => $title,
                    'body' => $body,
                    'discount_type' => 'percentage',
                    'discount_value' => 20,
                    'coupon_code' => 'VIPMEMBER',
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
                    ])
                    ->sendToDatabase($admins);

                $this->info("      ✉️  Notificación enviada");
            }
        }

        $this->line('');
        $this->info('✅ Sistema de Fidelización completado.');

        return Command::SUCCESS;
    }
}