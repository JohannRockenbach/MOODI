<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f3f4f6;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
        <tr>
            <td style="padding: 20px 0; text-align: center;">
                {{-- Contenedor Principal --}}
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    
                    {{-- Cabecera (Color MOODI) --}}
                    <tr>
                        <td style="background-color: #111827; padding: 40px 40px; text-align: center;">
                            {{-- Logo o Icono --}}
                            <div style="font-size: 48px; margin-bottom: 10px;">🍔 MOODI</div>
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: bold;">{{ $title }}</h1>
                        </td>
                    </tr>

                    {{-- Cuerpo del Mensaje --}}
                    <tr>
                        <td style="padding: 40px; color: #374151; font-size: 16px; line-height: 1.6;">
                            
                            {{-- Texto Principal (Renderizado como Markdown/HTML) --}}
                            <div style="margin-bottom: 30px;">
                                {!! nl2br(e($body)) !!}
                            </div>

                            {{-- Panel de Descuento (Si existe cupón) --}}
                            @if(!empty($couponCode))
                            <div style="background-color: #fef3c7; border: 2px dashed #d97706; border-radius: 8px; padding: 20px; text-align: center; margin-bottom: 30px;">
                                <p style="margin: 0 0 10px 0; color: #92400e; font-weight: bold; text-transform: uppercase; font-size: 12px;">Tu Código de Descuento</p>
                                <div style="font-size: 28px; font-weight: 900; color: #b45309; letter-spacing: 2px;">{{ $couponCode }}</div>
                                @if(!empty($discountText))
                                    <p style="margin: 10px 0 0 0; color: #92400e; font-size: 14px;">{{ $discountText }}</p>
                                @endif
                                @if(!empty($validUntil))
                                    <p style="margin: 5px 0 0 0; color: #b45309; font-size: 12px;">Válido hasta: {{ $validUntil }}</p>
                                @endif
                            </div>
                            @endif

                            {{-- Botón de Acción --}}
                            <div style="text-align: center;">
                                <a href="{{ $actionUrl }}" style="display: inline-block; background-color: #ef4444; color: #ffffff; text-decoration: none; padding: 16px 32px; border-radius: 50px; font-weight: bold; font-size: 16px; text-transform: uppercase; letter-spacing: 1px;">
                                    ¡Quiero mi Promo! 👉
                                </a>
                            </div>

                        </td>
                    </tr>

                    {{-- Pie de Página --}}
                    <tr>
                        <td style="background-color: #f9fafb; padding: 20px; text-align: center; color: #9ca3af; font-size: 12px; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0 0 10px 0;">Estás recibiendo esto porque te encanta la buena comida en MOODI.</p>
                            <p style="margin: 0;">{{ config('app.name') }} &copy; {{ date('Y') }}</p>
                        </td>
                    </tr>
                </table>
                {{-- Espacio final --}}
                <div style="height: 40px;"></div>
            </td>
        </tr>
    </table>
</body>
</html>
