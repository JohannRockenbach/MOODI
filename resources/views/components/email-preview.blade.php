<div style="max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
    <!-- Header -->
    <div style="background-color: #111827; padding: 20px; text-align: center;">
        <h1 style="color: white; margin: 0; font-size: 24px; font-weight: bold;">
            🍴 MOODI Restaurant
        </h1>
    </div>
    
    <!-- Contenido -->
    <div style="padding: 30px;">
        <h2 style="color: #1f2937; margin: 0 0 20px 0;">{{ $subject ?? 'Asunto del email' }}</h2>
        
        <div style="color: #4b5563; line-height: 1.6; margin-bottom: 30px;">
            {!! \Illuminate\Support\Str::markdown($body ?? '<p>Contenido del email...</p>') !!}
        </div>
        
        @if(!empty($discount_value))
        <!-- Cupón -->
        <div style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); padding: 25px; border-radius: 12px; text-align: center; margin-bottom: 25px; border: 2px solid #f59e0b;">
            <p style="margin: 0 0 10px 0; color: #78350f; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">
                🎉 Tu Cupón de Descuento
            </p>
            <p style="margin: 0; font-size: 32px; font-weight: bold; color: #92400e; letter-spacing: 2px; font-family: monospace;">
                {{ $coupon_code ?? 'CODIGO123' }}
            </p>
            <p style="margin: 10px 0 0 0; color: #92400e; font-size: 18px; font-weight: 600;">
                {{ $discount_type === 'percentage' ? $discount_value . '% OFF' : '$' . $discount_value . ' OFF' }}
            </p>
            @if(!empty($valid_until))
                <p style="margin: 5px 0 0 0; color: #b45309; font-size: 12px;">
                    Válido hasta: {{ $valid_until }} o hasta agotar stock
                </p>
            @endif
        </div>
        @endif
        
        <!-- CTA Button -->
        <div style="text-align: center; margin-top: 30px;">
            <a href="#" style="display: inline-block; background-color: #dc2626; color: white; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600; font-size: 16px; box-shadow: 0 4px 6px rgba(220, 38, 38, 0.3);">
                🛒 Hacer mi Pedido Ahora
            </a>
        </div>
    </div>
    
    <!-- Footer -->
    <div style="background-color: #f9fafb; padding: 20px; text-align: center; border-top: 1px solid #e5e7eb;">
        <p style="color: #6b7280; font-size: 12px; margin: 0;">
            © 2025 MOODI Restaurant. Todos los derechos reservados.
        </p>
    </div>
</div>
