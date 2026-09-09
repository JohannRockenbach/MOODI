<?php

namespace App\Console\Commands;

use App\Mail\PromoEmail;
use App\Models\CampaignDraft;
use App\Support\CampaignSegment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendScheduledCampaigns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'campaign:send-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envía las campañas programadas (status=scheduled) cuya fecha de envío ya venció';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dueCampaigns = CampaignDraft::query()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_date')
            ->where('scheduled_date', '<=', now())
            ->get();

        if ($dueCampaigns->isEmpty()) {
            $this->info('✅ No hay campañas programadas pendientes de envío.');

            return Command::SUCCESS;
        }

        $sent = 0;
        $failed = 0;

        foreach ($dueCampaigns as $campaign) {
            try {
                $restaurantId = $campaign->restaurant_id ?? 1;
                $clientes = CampaignSegment::clientesForSegment($campaign->segment, $restaurantId);

                if ($clientes->isEmpty()) {
                    // Sin destinatarios: se conserva como 'scheduled' para reintentar
                    // cuando haya clientes con email en el segmento.
                    $this->warn("⚠️  Campaña #{$campaign->id} \"{$campaign->subject}\": sin clientes destino para el segmento '{$campaign->segment}'. Se reintentará en la próxima ejecución.");
                    continue;
                }

                $mail = new PromoEmail(
                    title: $campaign->subject,
                    body: $campaign->body,
                    actionUrl: config('app.url'),
                    couponCode: $campaign->coupon_code,
                    discountText: CampaignSegment::formatDiscountText($campaign->discount_type, $campaign->discount_value),
                    validUntil: CampaignSegment::formatValidUntil($campaign->valid_until),
                );

                foreach ($clientes as $cliente) {
                    Mail::to($cliente->email)->send($mail);
                }

                $campaign->update(['status' => 'sent']);

                $this->info("✉️  Campaña #{$campaign->id} \"{$campaign->subject}\" enviada a {$clientes->count()} cliente(s).");
                $sent++;
            } catch (\Exception $e) {
                $failed++;
                Log::error("Error enviando campaña programada #{$campaign->id}", [
                    'error' => $e->getMessage(),
                ]);
                $this->error("❌ Campaña #{$campaign->id}: {$e->getMessage()}");
            }
        }

        $this->info("✅ Proceso finalizado: {$sent} enviada(s), {$failed} con error.");

        return Command::SUCCESS;
    }
}
