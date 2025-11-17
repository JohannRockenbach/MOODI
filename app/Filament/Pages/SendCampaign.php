<?php

namespace App\Filament\Pages;

use App\Mail\PromoEmail;
use Filament\Actions\Action;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Mail;

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

    // Propiedades públicas del formulario
    public string $subject = '';
    public string $body = '';
    public string $testEmail = '';

    /**
     * Mount: Pre-llenar datos desde la URL
     */
    public function mount(): void
    {
        $this->subject = request()->query('subject', '');
        $this->body = request()->query('body', '');
        $this->testEmail = auth()->user()->email; // Default al usuario actual
    }

    /**
     * Configuración del formulario
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Contenido de la Campaña')
                    ->description('Define el asunto y el cuerpo del correo promocional')
                    ->schema([
                        TextInput::make('subject')
                            ->label('Asunto')
                            ->placeholder('Ej: ¡Promo Especial de Lluvia!')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        RichEditor::make('body')
                            ->label('Contenido del Correo')
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
                            ->columnSpanFull(),
                    ])
                    ->columns(1),

                Section::make('Prueba de Envío')
                    ->description('Envía un correo de prueba para verificar el contenido')
                    ->schema([
                        TextInput::make('testEmail')
                            ->label('Enviar prueba a...')
                            ->email()
                            ->required()
                            ->placeholder('tu@email.com')
                            ->helperText('El correo se enviará a esta dirección.')
                            ->suffixAction(
                                FormAction::make('sendTest')
                                    ->label('Enviar Prueba')
                                    ->icon('heroicon-o-paper-airplane')
                                    ->color('success')
                                    ->action('sendTest')
                            ),
                    ])
                    ->collapsible(),
            ]);
    }

    /**
     * Enviar correo de prueba
     */
    public function sendTest(): void
    {
        // Validar que haya un email
        if (empty($this->testEmail)) {
            Notification::make()
                ->title('Error de validación')
                ->body('Por favor ingresa un email para enviar la prueba.')
                ->danger()
                ->send();
            return;
        }

        if (empty($this->subject) || empty($this->body)) {
            Notification::make()
                ->title('Error de validación')
                ->body('Por favor completa el asunto y el cuerpo del correo.')
                ->danger()
                ->send();
            return;
        }

        try {
            // Enviar email de prueba usando PromoEmail
            Mail::to($this->testEmail)->send(
                new PromoEmail(
                    title: $this->subject,
                    body: $this->body,
                    actionUrl: config('app.url')
                )
            );

            Notification::make()
                ->title('✅ Correo de prueba enviado')
                ->body("Correo de prueba enviado a {$this->testEmail}")
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('❌ Error al enviar')
                ->body("No se pudo enviar el correo: {$e->getMessage()}")
                ->danger()
                ->send();
        }
    }

    /**
     * Acciones de la página (header)
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendMassive')
                ->label('Enviar Campaña Masiva')
                ->icon('heroicon-o-megaphone')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('¿Enviar campaña a todos los clientes?')
                ->modalDescription('Esta acción enviará el email a todos los usuarios registrados. Asegúrate de haber enviado una prueba primero.')
                ->modalSubmitActionLabel('Sí, enviar a todos')
                ->action('sendMassiveCampaign')
                ->visible(fn() => !empty($this->subject) && !empty($this->body)),
        ];
    }

    /**
     * Enviar campaña masiva (placeholder para futuro)
     */
    public function sendMassiveCampaign(): void
    {
        // TODO: Implementar envío masivo con Queue
        Notification::make()
            ->title('🚀 Funcionalidad en desarrollo')
            ->body('El envío masivo estará disponible próximamente. Por ahora usa el botón de prueba.')
            ->warning()
            ->send();
    }
}
