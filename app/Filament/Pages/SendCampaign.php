<?php

namespace App\Filament\Pages;

use App\Mail\PromoEmail;
use App\Models\Cliente;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SendCampaign extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static string $view = 'filament.pages.send-campaign';

    protected static ?string $title = 'Enviar Campaña de Marketing';

    protected static ?string $navigationLabel = 'Campañas';

    protected static ?string $navigationGroup = 'Marketing';

    // Ocultar del menú de navegación (solo acceso por URL)
    protected static bool $shouldRegisterNavigation = false;

    // ==========================================
    // Propiedades públicas del formulario
    // ==========================================
    
    // Configuración de la promoción
    public ?int $product_id = null;
    public string $discount_type = 'percentage';
    public ?float $discount_value = null;
    public ?string $coupon_code = null;
    public ?string $valid_until = null;
    
    // Contenido del email
    public string $subject = '';
    public string $body = '';
    
    // Email de prueba
    public string $testEmail = '';

    /**
     * Mount: Pre-llenar datos desde la URL
     */
    public function mount(): void
    {
        // Cargar desde query strings
        $this->product_id = request()->query('product_id', null);
        $this->discount_type = request()->query('discount_type', 'percentage');
        $this->discount_value = request()->query('discount_value', null);
        $this->coupon_code = request()->query('coupon_code', null);
        $this->valid_until = request()->query('valid_until', null);
        $this->subject = request()->query('subject', '');
        $this->body = request()->query('body', '');
        
        // Defaults inteligentes
        if (empty($this->coupon_code)) {
            $this->coupon_code = strtoupper(Str::random(8));
        }
        
        if (empty($this->valid_until)) {
            $this->valid_until = now()->addDays(7)->format('Y-m-d');
        }
        
        $this->testEmail = Auth::check() && Auth::user() ? Auth::user()->email : '';
        
        // Si hay un producto, pre-llenar el asunto
        if ($this->product_id && empty($this->subject)) {
            $product = Product::find($this->product_id);
            if ($product) {
                $this->subject = "🎉 ¡Promoción especial en {$product->name}!";
            }
        }
    }

    /**
     * Configuración del formulario profesional en 2 columnas
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(2)
                    ->schema([
                        // ==========================================
                        // COLUMNA IZQUIERDA: Configuración de la Promoción
                        // ==========================================
                        Section::make('⚙️ Configuración de la Promoción')
                            ->description('Define los detalles del descuento y producto')
                            ->schema([
                                Select::make('product_id')
                                    ->label('Producto en Promoción')
                                    ->placeholder('Selecciona un producto (opcional)')
                                    ->options(Product::pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Deja vacío si es una promoción general')
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        // Auto-actualizar asunto si hay producto
                                        if ($state && empty($this->subject)) {
                                            $product = Product::find($state);
                                            if ($product) {
                                                $set('subject', "🎉 ¡Promoción especial en {$product->name}!");
                                            }
                                        }
                                    }),

                                Grid::make(2)
                                    ->schema([
                                        Select::make('discount_type')
                                            ->label('Tipo de Descuento')
                                            ->options([
                                                'percentage' => '% Porcentaje',
                                                'fixed' => '$ Monto Fijo',
                                            ])
                                            ->default('percentage')
                                            ->required()
                                            ->reactive()
                                            ->native(false),

                                        TextInput::make('discount_value')
                                            ->label(fn ($get) => $get('discount_type') === 'percentage' ? 'Porcentaje (%)' : 'Monto Fijo ($)')
                                            ->numeric()
                                            ->required()
                                            ->minValue(0)
                                            ->maxValue(fn ($get) => $get('discount_type') === 'percentage' ? 100 : null)
                                            ->suffix(fn ($get) => $get('discount_type') === 'percentage' ? '%' : '$')
                                            ->placeholder('Ej: 20'),
                                    ]),

                                TextInput::make('coupon_code')
                                    ->label('Código de Cupón')
                                    ->required()
                                    ->maxLength(50)
                                    ->placeholder('Ej: LLUVIA2025')
                                    ->helperText('Este código se mostrará en el email')
                                    ->suffixAction(
                                        FormAction::make('generate')
                                            ->icon('heroicon-o-sparkles')
                                            ->action(function (callable $set) {
                                                $set('coupon_code', strtoupper(Str::random(8)));
                                            })
                                    ),

                                DatePicker::make('valid_until')
                                    ->label('Válido Hasta')
                                    ->required()
                                    ->native(false)
                                    ->minDate(now())
                                    ->default(now()->addDays(7))
                                    ->displayFormat('d/m/Y')
                                    ->helperText('Fecha límite de la promoción'),
                            ])
                            ->columnSpan(1)
                            ->collapsible(),

                        // ==========================================
                        // COLUMNA DERECHA: Contenido del Email
                        // ==========================================
                        Section::make('✉️ Contenido del Email')
                            ->description('Personaliza el mensaje que recibirán los clientes')
                            ->schema([
                                TextInput::make('subject')
                                    ->label('Asunto del Correo')
                                    ->placeholder('Ej: ¡Promo Especial de Lluvia!')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),

                                RichEditor::make('body')
                                    ->label('Cuerpo del Mensaje')
                                    ->placeholder('Escribe aquí el contenido del email...')
                                    ->required()
                                    ->toolbarButtons([
                                        'bold',
                                        'italic',
                                        'underline',
                                        'strike',
                                        'h2',
                                        'h3',
                                        'bulletList',
                                        'orderedList',
                                        'link',
                                    ])
                                    ->columnSpanFull()
                                    ->helperText('El descuento y código de cupón se agregarán automáticamente al email'),
                            ])
                            ->columnSpan(1)
                            ->collapsible(),
                    ]),

                // ==========================================
                // Sección de Prueba (Ancho completo)
                // ==========================================
                Section::make('🧪 Enviar Email de Prueba')
                    ->description('Verifica cómo se verá el email antes de enviarlo masivamente')
                    ->schema([
                        TextInput::make('testEmail')
                            ->label('Email de prueba')
                            ->email()
                            ->required()
                            ->placeholder('tu@email.com')
                            ->helperText('El correo se enviará a esta dirección para que lo revises.')
                            ->live(onBlur: true),
                        
                        Actions::make([
                            FormAction::make('sendTestEmail')
                                ->label('📤 Enviar Email de Prueba')
                                ->icon('heroicon-o-paper-airplane')
                                ->color('success')
                                ->requiresConfirmation()
                                ->modalHeading('¿Enviar email de prueba?')
                                ->modalDescription(fn () => "Se enviará un correo de prueba a: " . ($this->testEmail ?? 'tu email'))
                                ->modalSubmitActionLabel('Sí, enviar')
                                ->modalIcon('heroicon-o-paper-airplane')
                                ->disabled(fn () => empty($this->testEmail) || empty($this->subject) || empty($this->body))
                                ->action(fn () => $this->sendTest()),
                        ])->fullWidth(),
                    ])
                    ->collapsible()
                    ->collapsed(false),
            ]);
    }

    /**
     * Enviar correo de prueba
     */
    public function sendTest(): void
    {
        // Validar campos requeridos
        if (empty($this->testEmail) || empty($this->subject) || empty($this->body)) {
            Notification::make()
                ->title('Error de validación')
                ->body('Por favor completa todos los campos requeridos.')
                ->danger()
                ->send();
            return;
        }

        try {
            // Formatear el descuento para mostrar
            $discountText = $this->formatDiscountText();
            $validUntilFormatted = $this->formatValidUntil();

            // Enviar email de prueba
            Mail::to($this->testEmail)->send(
                new PromoEmail(
                    title: $this->subject,
                    body: $this->body,
                    actionUrl: config('app.url'),
                    couponCode: $this->coupon_code,
                    discountText: $discountText,
                    validUntil: $validUntilFormatted
                )
            );

            Notification::make()
                ->title('✅ Correo de prueba enviado')
                ->body("Email enviado exitosamente a {$this->testEmail}. Revisa tu bandeja de entrada.")
                ->success()
                ->duration(5000)
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('❌ Error al enviar')
                ->body("No se pudo enviar el correo: {$e->getMessage()}")
                ->danger()
                ->persistent()
                ->send();
        }
    }

    /**
     * Acciones del header (botón de envío masivo)
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendMassive')
                ->label('📣 Enviar Campaña Masiva')
                ->icon('heroicon-o-megaphone')
                ->color('danger')
                ->size('lg')
                ->requiresConfirmation()
                ->modalHeading('⚠️ ¿Enviar campaña a TODOS los clientes?')
                ->modalDescription(function () {
                    $count = Cliente::whereNotNull('email')->count();
                    return "Esta acción enviará el email a **{$count} clientes** con email registrado. Asegúrate de haber enviado una prueba primero.";
                })
                ->modalSubmitActionLabel('Sí, enviar a todos')
                ->modalIcon('heroicon-o-megaphone')
                ->action('sendMassiveCampaign')
                ->visible(fn() => !empty($this->subject) && !empty($this->body) && !empty($this->discount_value)),
        ];
    }

    /**
     * Enviar campaña masiva a todos los clientes
     */
    public function sendMassiveCampaign(): void
    {
        try {
            // Obtener todos los clientes con email
            $clientes = Cliente::whereNotNull('email')->get();

            if ($clientes->isEmpty()) {
                Notification::make()
                    ->title('⚠️ No hay clientes')
                    ->body('No se encontraron clientes con email registrado.')
                    ->warning()
                    ->send();
                return;
            }

            // Formatear datos
            $discountText = $this->formatDiscountText();
            $validUntilFormatted = $this->formatValidUntil();

            // Contador de emails enviados
            $sent = 0;
            $failed = 0;

            foreach ($clientes as $cliente) {
                try {
                    Mail::to($cliente->email)->send(
                        new PromoEmail(
                            title: $this->subject,
                            body: $this->body,
                            actionUrl: config('app.url'),
                            couponCode: $this->coupon_code,
                            discountText: $discountText,
                            validUntil: $validUntilFormatted
                        )
                    );
                    $sent++;
                } catch (\Exception $e) {
                    $failed++;
                    Log::error("Error enviando email a {$cliente->email}: {$e->getMessage()}");
                }
            }

            // Notificación de éxito
            Notification::make()
                ->title('🎉 Campaña enviada exitosamente')
                ->body("✅ **{$sent}** emails enviados correctamente" . ($failed > 0 ? "\n❌ **{$failed}** emails fallaron (ver logs)" : ""))
                ->success()
                ->duration(10000)
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('❌ Error crítico')
                ->body("No se pudo completar el envío masivo: {$e->getMessage()}")
                ->danger()
                ->persistent()
                ->send();
        }
    }

    /**
     * Formatear el texto del descuento para mostrar en el email
     */
    private function formatDiscountText(): string
    {
        if ($this->discount_type === 'percentage') {
            return "{$this->discount_value}% de descuento";
        } else {
            return "\${$this->discount_value} de descuento";
        }
    }

    /**
     * Formatear la fecha de validez
     */
    private function formatValidUntil(): string
    {
        if (empty($this->valid_until)) {
            return '';
        }

        return \Carbon\Carbon::parse($this->valid_until)->format('d/m/Y');
    }
}
