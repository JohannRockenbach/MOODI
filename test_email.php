<?php

/**
 * Script de prueba de envío de email
 * 
 * Para ejecutar en tinker:
 * php artisan tinker
 * 
 * Luego pega este código:
 */

use Illuminate\Support\Facades\Mail;
use App\Mail\PromoEmail;

// Crear un email de prueba
$email = new PromoEmail(
    title: '🎉 Prueba de Configuración SMTP',
    body: 'Este es un correo de prueba para verificar que la configuración de Gmail SMTP está funcionando correctamente. Si recibes este mensaje, ¡todo está configurado correctamente!',
    actionUrl: 'https://moodi.com'
);

// Enviar el email
Mail::to('rockenbachjohann@gmail.com')->send($email);

echo "✅ Email enviado correctamente a rockenbachjohann@gmail.com\n";
echo "📧 Revisa tu bandeja de entrada (y spam si no lo ves)\n";
