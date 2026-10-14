<?php

namespace App\Services;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

/**
 * QR de pago (futura actualización): genera la imagen QR que codifica una
 * URL INTERNA opaca (/pago-qr/{token}). El QR NO procesa ningún pago: al
 * escanearlo se muestra un aviso de que el pago por QR estará disponible
 * en una futura actualización (sin datos de la venta, sin montos, sin
 * nombres del cliente).
 *
 * El token es un sha256 (64 hex) derivado de un identificador de la
 * operación (p. ej. id + created_at de la Sale) — opaco y verificable solo
 * por formato en la ruta pública (no se consulta la DB).
 */
class PaymentQrService
{
    /**
     * Generar el QR (PNG → data URI) para un monto y un identificador de
     * operación. El monto NO se codifica dentro del QR: el contenido es
     * únicamente la URL interna con el token opaco.
     *
     * @param  float|string  $total  Monto total de la operación (se usa como
     *                               insumo del identificador; no se revela).
     * @param  string  $saleIdentifier  Identificador opaco de la operación.
     *
     * @return array{token: string, url: string, image: string}
     */
    public function generate(float|string $total, string $saleIdentifier): array
    {
        $token = hash('sha256', $saleIdentifier);
        $url = $this->paymentUrl($token);

        $qrCode = new QrCode(
            data: $url,
            size: 240,
            margin: 10,
        );

        $image = (new PngWriter())->write($qrCode)->getDataUri();

        return [
            'token' => $token,
            'url' => $url,
            'image' => $image,
        ];
    }

    /**
     * URL interna del aviso de pago por QR (futura actualización).
     */
    public function paymentUrl(string $token): string
    {
        return url('/pago-qr/'.$token);
    }
}