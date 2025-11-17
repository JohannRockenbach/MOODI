# Ejemplo de Uso: Sistema de Correos de Promociones

## 📧 Cómo Enviar una Promoción

### Ejemplo Básico

```php
use App\Mail\PromoEmail;
use Illuminate\Support\Facades\Mail;

// Enviar a un cliente específico
Mail::to('cliente@example.com')->send(
    new PromoEmail(
        title: '🍔 ¡50% OFF en Hamburguesas!',
        body: 'Esta semana tenemos una promoción especial. Todas nuestras hamburguesas tienen 50% de descuento. ¡No te lo pierdas!',
        actionUrl: 'https://turestaurante.com/promociones'
    )
);
```

### Enviar a Múltiples Clientes

```php
use App\Models\Cliente;
use App\Mail\PromoEmail;
use Illuminate\Support\Facades\Mail;

// Obtener todos los clientes que quieren recibir promociones
$clientes = Cliente::where('acepta_promociones', true)->get();

foreach ($clientes as $cliente) {
    Mail::to($cliente->email)->send(
        new PromoEmail(
            title: '🎉 Promoción Especial para Ti',
            body: "Hola {$cliente->name}, tenemos una oferta exclusiva solo para ti...",
            actionUrl: 'https://turestaurante.com/promo-especial'
        )
    );
}
```

### Enviar con Cola (Recomendado para Muchos Emails)

```php
use App\Mail\PromoEmail;
use Illuminate\Support\Facades\Mail;

Mail::to('cliente@example.com')->queue(
    new PromoEmail(
        title: '🌟 Nueva Carta de Verano',
        body: 'Descubre nuestros nuevos platos frescos y deliciosos. Perfectos para el verano.',
        actionUrl: 'https://turestaurante.com/menu-verano'
    )
);
```

## 🧪 Probar el Envío de Correos

### Opción 1: Usar Mailpit (Desarrollo Local)

En tu `.env`:
```env
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
```

Luego accede a: http://localhost:8025

### Opción 2: Usar Gmail (Producción)

En tu `.env`:
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tu-email@gmail.com
MAIL_PASSWORD=tu-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=tu-email@gmail.com
MAIL_FROM_NAME="${APP_NAME}"
```

**Importante:** Necesitas generar una "App Password" en tu cuenta de Gmail:
https://myaccount.google.com/apppasswords

### Comando de Prueba en Tinker

```bash
php artisan tinker
```

```php
use App\Mail\PromoEmail;
use Illuminate\Support\Facades\Mail;

Mail::to('tu-email@gmail.com')->send(
    new PromoEmail(
        title: '🎊 Prueba de Promoción',
        body: 'Este es un correo de prueba para verificar que todo funciona correctamente.',
        actionUrl: 'https://google.com'
    )
);
```

## 🎨 Personalizar el Diseño

Para personalizar colores y estilos, publica los componentes de correo:

```bash
php artisan vendor:publish --tag=laravel-mail
```

Luego edita: `resources/views/vendor/mail/html/themes/default.css`

## 📊 Crear una Acción en Filament para Enviar Promociones

Puedes crear un botón en tu panel de admin:

```php
Tables\Actions\Action::make('sendPromo')
    ->label('Enviar Promoción')
    ->icon('heroicon-o-envelope')
    ->form([
        Forms\Components\TextInput::make('title')
            ->label('Título')
            ->required(),
        Forms\Components\Textarea::make('body')
            ->label('Mensaje')
            ->required(),
        Forms\Components\TextInput::make('url')
            ->label('URL del Botón')
            ->url()
            ->required(),
    ])
    ->action(function (array $data, $record) {
        Mail::to($record->email)->send(
            new PromoEmail(
                title: $data['title'],
                body: $data['body'],
                actionUrl: $data['url']
            )
        );
        
        Notification::make()
            ->success()
            ->title('Correo Enviado')
            ->body('La promoción fue enviada a ' . $record->email)
            ->send();
    })
```

## 🔔 Tips

1. **Siempre prueba primero** con tu propio correo
2. **Usa colas** para envíos masivos: `php artisan queue:work`
3. **Respeta la privacidad**: Solo envía a quienes aceptaron promociones
4. **No hagas spam**: Limita la frecuencia de envíos
5. **Monitorea rebotes**: Revisa correos que no se entregaron

## 📝 Logs

Los correos enviados se registran en:
- `storage/logs/laravel.log`
- Si usas `MAIL_MAILER=log`, los correos aparecen ahí completos
