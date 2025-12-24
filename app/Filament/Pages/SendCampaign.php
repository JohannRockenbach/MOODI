<?php

namespace App\Filament\Pages;

use App\Mail\PromoEmail;
use App\Models\Cliente;
use App\Models\Product;
use App\Models\Category;
use App\Models\Recipe;
use App\Models\Restaurant;
use App\Models\CampaignDraft;
use App\Models\IngredientBatch;
use Filament\Actions\Action;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Split;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\HtmlString;

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
    public ?string $template_selector = null;
    
    // Email de prueba
    public string $testEmail = '';
    
    // Automatización avanzada
    public bool $enable_automation = false;
    public ?string $automation_datetime = null;
    
    // Recetas sugeridas para anti-desperdicio
    public array $suggested_recipes = [];
    public array $suggested_campaigns = [];
    public bool $is_stock_campaign = false;
    
    // Parámetros de clima
    public ?float $temp_threshold = null;
    public ?float $rain_threshold = null;

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
        
        // Cargar sugerencias del Chef automáticamente
        $this->loadChefSuggestions();
        
        // Receta sugerida del Chef Inteligente
        $suggestedRecipe = request()->query('suggested_recipe', null);
        if ($suggestedRecipe) {
            try {
                $recipe = json_decode(base64_decode($suggestedRecipe), true);
                if ($recipe) {
                    $this->subject = "🔥 Nueva Creación: {$recipe['name']}!";
                    $this->body = "{$recipe['description']}. Oferta especial de lanzamiento con descuento exclusivo.\n\n¡Prueba esta edición limitada antes que se acabe!";
                    $this->discount_value = 20;
                    $this->discount_type = 'percentage';
                    
                    Notification::make()
                        ->title('👨‍🍳 Receta del Chef Cargada')
                        ->body("Campaña preparada para: {$recipe['name']}")
                        ->success()
                        ->send();
                }
            } catch (\Exception $e) {
                // Silenciar errores de decodificación
            }
        }
        
        // Defaults inteligentes
        if (empty($this->coupon_code)) {
            $this->coupon_code = strtoupper(Str::random(8));
        }
        
        if (empty($this->valid_until)) {
            $this->valid_until = now()->addDays(7)->format('Y-m-d');
        }
        
        $this->testEmail = 'rockenbachjohann@gmail.com'; // Email fijo para demo
        
        // Si hay un producto, pre-llenar el asunto
        if ($this->product_id && empty($this->subject)) {
            $product = Product::find($this->product_id);
            if ($product) {
                $this->subject = "🎉 ¡Promoción especial en {$product->name}!";
            }
        }
        
        // Cargar sugerencias en tiempo real
        $this->loadSuggestions();
    }

    /**
     * Constructor avanzado de campañas con layout Split
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // ==========================================
                // SECCIÓN SUPERIOR: Sugerencias del Sistema
                // ==========================================
                Section::make('💡 Sugerencias del Sistema')
                    ->description('Campañas detectadas automáticamente basadas en el inventario actual')
                    ->schema([
                        ViewField::make('suggestions_view')
                            ->view('filament.components.campaign-suggestions', [
                                'suggestions' => $this->suggested_campaigns,
                            ])
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(false)
                    ->extraAttributes([
                        'x-data' => '{}',
                    ])
                    ->footerActions([
                        \Filament\Forms\Components\Actions\Action::make('refreshSuggestions')
                            ->label('🔄 Actualizar Sugerencias')
                            ->icon('heroicon-o-arrow-path')
                            ->color('info')
                            ->action(function () {
                                $this->loadSuggestions();
                                \Filament\Notifications\Notification::make()
                                    ->title('✅ Sugerencias actualizadas')
                                    ->body('Se recargaron las oportunidades del sistema.')
                                    ->success()
                                    ->send();
                            }),
                    ]),
                
                Split::make([
                    // ==========================================
                    // SECCIÓN PRINCIPAL: Editor de Contenido
                    // ==========================================
                    Section::make('✉️ Editor de Campaña')
                        ->schema([
                            Select::make('template_selector')
                                ->label('💡 Plantillas Creativas')
                                ->placeholder('Selecciona una frase predefinida')
                                ->options(function () {
                                    $restaurant = Restaurant::find(1);
                                    $messages = $restaurant?->marketing_settings['promo_messages'] ?? [];
                                    
                                    if (empty($messages)) {
                                        return ['' => 'No hay frases configuradas'];
                                    }
                                    
                                    $options = [];
                                    foreach ($messages as $index => $message) {
                                        $options[$index] = $message['title'] ?? "Frase {$index}";
                                    }
                                    return $options;
                                })
                                ->reactive()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    if ($state === null || $state === '') return;
                                    
                                    $restaurant = Restaurant::find(1);
                                    $messages = $restaurant?->marketing_settings['promo_messages'] ?? [];
                                    
                                    if (isset($messages[$state])) {
                                        $template = $messages[$state];
                                        $set('subject', $template['title'] ?? '');
                                        $set('body', $template['body'] ?? '');
                                        
                                        Notification::make()
                                            ->title('✨ Plantilla aplicada')
                                            ->body('El asunto y cuerpo se actualizaron con la frase seleccionada.')
                                            ->success()
                                            ->send();
                                    }
                                })
                                ->helperText('💡 Tip: Configura frases en "Configuración de Marketing"')
                                ->native(false),
                                
                            TextInput::make('subject')
                                ->label('Asunto del Correo')
                                ->placeholder('Ej: ¡Promo Especial de Lluvia!')
                                ->required()
                                ->maxLength(255),

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
                                ->helperText('El descuento y código de cupón se agregarán automáticamente')
                                ->columnSpanFull(),
                            
                            Placeholder::make('preview')
                                ->label('👀 Vista Previa del Email')
                                ->content(fn () => new HtmlString($this->getEmailPreview()))
                                ->extraAttributes(['class' => 'border rounded-lg p-4 bg-gray-50'])
                                ->columnSpanFull(),
                        ])
                        ->grow(true),

                    // ==========================================
                    // SIDEBAR: Configuración y Acciones
                    // ==========================================
                    Section::make('⚙️ Configuración')
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
                                ->label(fn ($get) => $get('discount_type') === 'percentage' ? 'Porcentaje' : 'Monto Fijo')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->maxValue(fn ($get) => $get('discount_type') === 'percentage' ? 100 : null)
                                ->suffix(fn ($get) => $get('discount_type') === 'percentage' ? '%' : '$'),

                            TextInput::make('coupon_code')
                                ->label('Código de Cupón')
                                ->required()
                                ->maxLength(50)
                                ->placeholder('Ej: LLUVIA2025')
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
                                ->displayFormat('d/m/Y'),

                            Toggle::make('enable_automation')
                                ->label('Programar Envío')
                                ->reactive()
                                ->live()
                                ->helperText('Enviar automáticamente en una fecha'),

                            DateTimePicker::make('automation_datetime')
                                ->label('Fecha y Hora de Envío')
                                ->seconds(false)
                                ->minutesStep(15)
                                ->native(false)
                                ->timezone('America/Argentina/Buenos_Aires')
                                ->minDate(now())
                                ->maxDate(now()->addMonths(1))
                                ->displayFormat('d/m/Y H:i')
                                ->dehydrated()
                                ->required(fn ($get) => $get('enable_automation'))
                                ->visible(fn ($get) => $get('enable_automation'))
                                ->helperText('📅 Selecciona día y hora exacta del envío'),

                            Actions::make([
                                FormAction::make('sendCampaign')
                                    ->label(fn ($get) => $get('enable_automation') ? '💾 Guardar Programación' : '📧 Enviar Campaña')
                                    ->icon(fn ($get) => $get('enable_automation') ? 'heroicon-o-clock' : 'heroicon-o-paper-airplane')
                                    ->color('success')
                                    ->requiresConfirmation()
                                    ->modalHeading(fn ($get) => $get('enable_automation') ? '💾 Guardar Programación' : '📧 Enviar Campaña')
                                    ->modalDescription(fn ($get) => $get('enable_automation') 
                                        ? 'Esta campaña se enviará automáticamente en la fecha programada.' 
                                        : 'Esta campaña se enviará a rockenbachjohann@gmail.com')
                                    ->modalSubmitActionLabel(fn ($get) => $get('enable_automation') ? 'Guardar' : 'Enviar ahora')
                                    ->action(fn ($get) => $get('enable_automation') ? $this->saveScheduledCampaign() : $this->sendDemoCampaign()),
                            ])->fullWidth(),
                        ])
                        ->grow(false),
                ])->from('md'),

                // ==========================================
                // SECCIÓN DE RECETAS SUGERIDAS DEL CHEF
                // ==========================================
                Section::make('👨‍🍳 Creaciones del Chef')
                    ->description('Recetas especiales generadas automáticamente con ingredientes por vencer')
                    ->schema([
                        ViewField::make('suggestions_preview')
                            ->view('filament.pages.partials.suggestions', [
                                'suggestions' => $this->suggested_recipes,
                            ])
                            ->columnSpanFull(),
                    ])
                    ->visible(fn () => !empty($this->suggested_recipes))
                    ->collapsible(),
            ]);
    }

    /**
     * Enviar campaña a email de demo
     */
    public function sendDemoCampaign()
    {
        // Validar campos requeridos
        if (empty($this->subject) || empty($this->body)) {
            Notification::make()
                ->title('Error de validación')
                ->body('Por favor completa el asunto y el cuerpo del mensaje.')
                ->danger()
                ->send();
            return;
        }

        try {
            $discountText = $this->formatDiscountText();
            $validUntilFormatted = $this->formatValidUntil();

            Mail::to('rockenbachjohann@gmail.com')->send(
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
                ->title('✅ Campaña enviada exitosamente')
                ->body('Email enviado a rockenbachjohann@gmail.com')
                ->success()
                ->duration(5000)
                ->send();
            
            // Redirect al escritorio después de enviar
            return redirect()->route('filament.admin.pages.dashboard');
            
        } catch (\Exception $e) {
            Notification::make()
                ->title('❌ Error al enviar')
                ->body("No se pudo enviar el correo: {$e->getMessage()}")
                ->danger()
                ->send();
        }
    }

    /**
     * Acciones del header (botones de guardar y enviar)
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('loadDraft')
                ->label('📂 Cargar Borrador')
                ->icon('heroicon-o-folder-open')
                ->color('info')
                ->form([
                    Select::make('draft_id')
                        ->label('Selecciona un borrador')
                        ->options(function () {
                            return CampaignDraft::where('user_id', Auth::id())
                                ->latest()
                                ->pluck('name', 'id');
                        })
                        ->required()
                        ->searchable(),
                ])
                ->action(function (array $data) {
                    $draft = CampaignDraft::find($data['draft_id']);
                    
                    if ($draft) {
                        $this->subject = $draft->subject;
                        $this->body = $draft->body;
                        $this->product_id = $draft->product_id;
                        $this->discount_type = $draft->discount_type;
                        $this->discount_value = $draft->discount_value;
                        $this->coupon_code = $draft->coupon_code;
                        $this->valid_until = $draft->valid_until?->format('Y-m-d');
                        
                        Notification::make()
                            ->title('✅ Borrador Cargado')
                            ->body("Campaña \"{$draft->name}\" cargada exitosamente.")
                            ->success()
                            ->send();
                    }
                }),
                
            Action::make('saveDraft')
                ->label('💾 Guardar Borrador')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->form([
                    TextInput::make('draft_name')
                        ->label('Nombre del Borrador')
                        ->placeholder('Ej: Promo Lluvia Diciembre')
                        ->required(),
                ])
                ->action(function (array $data) {
                    CampaignDraft::create([
                        'user_id' => Auth::id(),
                        'name' => $data['draft_name'],
                        'subject' => $this->subject,
                        'body' => $this->body,
                        'product_id' => $this->product_id,
                        'discount_type' => $this->discount_type,
                        'discount_value' => $this->discount_value,
                        'coupon_code' => $this->coupon_code,
                        'valid_until' => $this->valid_until,
                    ]);
                    
                    Notification::make()
                        ->title('✅ Borrador Guardado')
                        ->body("La campaña \"{$data['draft_name']}\" se guardó exitosamente.")
                        ->success()
                        ->duration(3000)
                        ->send();
                }),
                
            Action::make('sendCampaign')
                ->label('📧 Enviar Campaña')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->size('lg')
                ->requiresConfirmation()
                ->modalHeading('📧 Enviar Campaña de Demo')
                ->modalDescription('Esta campaña se enviará a rockenbachjohann@gmail.com para demostración.')
                ->modalSubmitActionLabel('Enviar ahora')
                ->action('sendDemoCampaign')
                ->visible(fn() => !empty($this->subject) && !empty($this->body)),
        ];
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
    
    /**
     * Cargar sugerencias del sistema en tiempo real
     */
    private function loadSuggestions(): void
    {
        $this->suggested_campaigns = [];
        
        // Ingredientes a ignorar
        $ignoredIngredients = [
            'Harina', 'Levadura', 'Sal', 'Azúcar', 'Agua', 'Aceite',
            'Papas Congeladas', 'Aceite de Oliva', 'Vinagre', 'Pimienta',
            'Huevo', 'Huevos',
        ];

        // Buscar lotes próximos a vencer
        $expiringBatches = IngredientBatch::where('quantity', '>', 0)
            ->where('expiration_date', '<=', now()->addDays(3))
            ->where('expiration_date', '>=', now())
            ->whereHas('ingredient', fn($q) => $q->whereNotIn('name', $ignoredIngredients))
            ->with('ingredient')
            ->get();

        if ($expiringBatches->isEmpty()) {
            return;
        }

        // Agrupar por ingrediente y tomar los 3 con mayor cantidad
        $topRisks = $expiringBatches->groupBy('ingredient_id')
            ->map(function ($batches) {
                $ingredient = $batches->first()->ingredient;
                return [
                    'ingredient' => $ingredient,
                    'total_quantity' => $batches->sum('quantity'),
                ];
            })
            ->sortByDesc('total_quantity')
            ->take(3);

        // Generar sugerencias variadas
        $copyVariations = [
            "🔥 ¡Semana de {product}! 2x1 Exclusivo",
            "🍔 El favorito de todos: {product} hoy con descuento",
            "✨ Especial del día: {product} con precio irresistible",
        ];

        $index = 0;
        foreach ($topRisks as $risk) {
            $ingredient = $risk['ingredient'];
            
            // Buscar producto que use este ingrediente
            $product = Product::whereHas('recipe.ingredients', function ($query) use ($ingredient) {
                $query->where('ingredients.id', $ingredient->id);
            })->first();

            if ($product) {
                $template = $copyVariations[$index % count($copyVariations)];
                $this->suggested_campaigns[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'title' => str_replace('{product}', $product->name, $template),
                    'body' => "Oferta especial en {$product->name}. ¡Aprovecha esta promoción limitada!",
                    'discount_value' => 20,
                    'coupon_code' => 'FLASH' . strtoupper(substr($product->name, 0, 3)),
                ];
                $index++;
            }
        }
    }
    
    /**
     * Usar una sugerencia del sistema
     */
    public function useSuggestion(int $index): void
    {
        if (!isset($this->suggested_campaigns[$index])) {
            return;
        }
        
        $suggestion = $this->suggested_campaigns[$index];
        
        $this->subject = $suggestion['title'];
        $this->body = $suggestion['body'];
        $this->product_id = $suggestion['product_id'];
        $this->discount_value = $suggestion['discount_value'];
        $this->discount_type = 'percentage';
        $this->coupon_code = $suggestion['coupon_code'];
        
        Notification::make()
            ->title('✅ Sugerencia aplicada')
            ->body("Campaña configurada para: {$suggestion['product_name']}")
            ->success()
            ->send();
    }
    
    /**
     * Detectar si es una campaña de stock y cargar recetas sugeridas
     */
    private function detectStockCampaign(): void
    {
        // Verificar si viene de una notificación de stock
        $campaignType = request()->query('type', null);
        
        if ($campaignType === 'stock' || stripos($this->subject, 'stock') !== false || stripos($this->subject, 'desperdicio') !== false) {
            $this->is_stock_campaign = true;
            $this->loadSuggestedRecipes();
        }
    }
    
    /**
     * Cargar recetas sugeridas basadas en ingredientes críticos
     */
    private function loadSuggestedRecipes(): void
    {
        // Ingredientes en riesgo (3 días o menos)
        $criticalBatches = IngredientBatch::where('quantity', '>', 0)
            ->where('expiration_date', '<=', now()->addDays(3))
            ->where('expiration_date', '>=', now())
            ->with('ingredient')
            ->get();
        
        $this->suggested_recipes = [];
        
        foreach ($criticalBatches as $batch) {
            // Buscar recetas que usen este ingrediente
            $recipe = Recipe::whereHas('ingredients', function ($query) use ($batch) {
                $query->where('ingredients.id', $batch->ingredient_id);
            })
            ->with('ingredients')
            ->first();
            
            if ($recipe) {
                // Calcular tiempo restante en formato legible
                $daysUntilExpiry = now()->floatDiffInDays($batch->expiration_date);
                
                if ($daysUntilExpiry < 1) {
                    $hours = ceil($daysUntilExpiry * 24);
                    $expiryText = "vence en {$hours} horas";
                } else {
                    $days = ceil($daysUntilExpiry);
                    $expiryText = "vence en {$days} días";
                }
                
                $this->suggested_recipes[] = [
                    'recipe_id' => $recipe->id,
                    'name' => $recipe->name,
                    'ingredient_id' => $batch->id,
                    'ingredient_name' => $batch->ingredient->name,
                    'quantity' => $batch->quantity,
                    'measurement_unit' => $batch->ingredient->measurement_unit,
                    'expiration_date' => $batch->expiration_date->format('d/m/Y'),
                    'expiry_text' => $expiryText,
                ];
            }
        }
    }
    
    /**
     * Generar texto predeterminado para campañas de stock
     */
    private function generateStockCampaignText(): void
    {
        if (empty($this->suggested_recipes)) {
            return;
        }
        
        $firstRecipe = $this->suggested_recipes[0];
        $recipeName = $firstRecipe['name'];
        
        // Generar asunto con copy de urgencia
        $this->subject = "🔥 ¡OFERTA FLASH: {$recipeName}!";
        
        // Generar cuerpo con copywriting de ventas
        $this->body = "¡Solo por hoy! Preparamos una tanda especial de {$recipeName} y queremos que la disfrutes con un descuento exclusivo.\n\n"
            . "🏃‍♂️ ¡Corre que vuelan! Esta oferta no dura mucho.";
    }
    
    /**
     * Usar una receta sugerida (actualizar campaña con sus datos)
     */
    public function useRecipe(int $index): void
    {
        if (!isset($this->suggested_recipes[$index])) {
            Notification::make()
                ->title('❌ Error')
                ->body('No se encontró la receta seleccionada.')
                ->danger()
                ->send();
            return;
        }
        
        $suggestion = $this->suggested_recipes[$index];
        
        // Actualizar campos de la campaña con la información disponible
        $this->subject = "🔥 ¡OFERTA FLASH: {$suggestion['name']}!";
        $this->body = "¡Solo por hoy! Preparamos una tanda especial de {$suggestion['name']} con extra {$suggestion['ingredient_name']}. Queremos que lo disfrutes con un descuento exclusivo.\n\n🏃‍♂️ ¡Corre que vuelan!";
        $this->discount_value = 20;
        $this->discount_type = 'percentage';
        $this->coupon_code = 'CHEF' . strtoupper(substr($suggestion['ingredient_name'], 0, 3));
        
        Notification::make()
            ->title('✨ Campaña actualizada')
            ->body("Asunto y cuerpo actualizados para: {$suggestion['name']}")
            ->success()
            ->send();
    }
    
    /**
     * Publicar receta como producto temporal
     */
    public function publishTemporalProduct(int $index): void
    {
        if (!isset($this->suggested_recipes[$index])) {
            Notification::make()
                ->title('❌ Error')
                ->body('No se encontró la receta seleccionada.')
                ->danger()
                ->send();
            return;
        }
        
        $suggestion = $this->suggested_recipes[$index];
        
        try {
            // Buscar o crear categoría "Temporales"
            $category = Category::firstOrCreate(
                ['name' => 'Temporales'],
                [
                    'description' => 'Menú temporal - Productos disponibles por tiempo limitado',
                    'is_active' => true,
                ]
            );
            
            // Verificar si ya existe un producto con este nombre
            $existingProduct = Product::where('name', $suggestion['name'])->first();
            
            if ($existingProduct) {
                Notification::make()
                    ->title('ℹ️ Producto existente')
                    ->body("**{$existingProduct->name}** ya está publicado en el menú.")
                    ->info()
                    ->duration(5000)
                    ->send();
                return;
            }
            
            // Crear el producto temporal
            $product = Product::create([
                'name' => $suggestion['name'],
                'description' => "Edición limitada con extra {$suggestion['ingredient_name']} - Hasta agotar stock",
                'category_id' => $category->id,
                'price' => $suggestion['suggested_price'] ?? 0,
                'is_available' => true,
            ]);
            
            Notification::make()
                ->title('✅ Plato Publicado')
                ->body("**{$product->name}** se agregó al Menú Temporal")
                ->success()
                ->duration(5000)
                ->send();
                
        } catch (\Exception $e) {
            Notification::make()
                ->title('❌ Error al publicar')
                ->body("No se pudo crear el producto: {$e->getMessage()}")
                ->danger()
                ->send();
        }
    }
    
    /**
     * Guardar campaña programada
     */
    public function saveScheduledCampaign()
    {
        // Validar que la fecha esté en el futuro
        if (!$this->scheduled_date || \Carbon\Carbon::parse($this->scheduled_date)->isPast()) {
            Notification::make()
                ->title('⚠️ Error de Validación')
                ->body('La fecha programada debe ser futura.')
                ->danger()
                ->send();
            return;
        }
        
        try {
            // Guardar en base de datos
            CampaignDraft::create([
                'user_id' => Auth::id(),
                'restaurant_id' => Auth::user()?->restaurant_id,
                'subject' => $this->subject,
                'body' => $this->body,
                'product_id' => $this->product_id,
                'coupon_code' => $this->coupon_code,
                'discount_type' => $this->discount_type,
                'discount_value' => $this->discount_value,
                'scheduled_date' => $this->scheduled_date,
                'valid_until' => $this->valid_until,
                'status' => 'scheduled', // Estado programado
            ]);
            
            Notification::make()
                ->title('💾 Campaña Programada')
                ->body('La campaña se enviará automáticamente en la fecha indicada.')
                ->success()
                ->duration(5000)
                ->send();
            
            // Redirect a escritorio
            return redirect()->route('filament.admin.pages.dashboard');
            
        } catch (\Exception $e) {
            Notification::make()
                ->title('❌ Error al guardar')
                ->body("No se pudo programar la campaña: {$e->getMessage()}")
                ->danger()
                ->send();
        }
    }
    
    /**
     * Cargar sugerencias del Chef Inteligente desde notificaciones recientes
     */
    private function loadChefSuggestions(): void
    {
        $this->suggested_recipes = [];
        
        // Buscar notificaciones recientes del Chef (últimas 24 horas)
        $recentNotifications = DB::table('notifications')
            ->where('type', 'Filament\Notifications\DatabaseNotification')
            ->where('created_at', '>=', now()->subDay())
            ->whereRaw("data->>'title' LIKE '%💡 Idea de Nuevo Plato%'")
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
        
        foreach ($recentNotifications as $notification) {
            $data = json_decode($notification->data, true);
            
            // Extraer información de la notificación
            if (isset($data['title'])) {
                preg_match('/💡 Idea de Nuevo Plato: (.+)/', $data['title'], $matches);
                $recipeName = $matches[1] ?? 'Receta Desconocida';
                
                // Extraer ingrediente y precio del body
                preg_match('/Exceso de \*\*(.+?)\*\*/', $data['body'], $ingredientMatch);
                preg_match('/Precio: \$(\d+)/', $data['body'], $priceMatch);
                
                $this->suggested_recipes[] = [
                    'recipe_id' => null,
                    'name' => $recipeName,
                    'ingredient_id' => null,
                    'ingredient_name' => $ingredientMatch[1] ?? 'N/A',
                    'suggested_price' => $priceMatch[1] ?? 0,
                ];
            }
        }
    }
    
    /**
     * Generar vista previa del email
     */
    private function getEmailPreview(): string
    {
        if (empty($this->subject) && empty($this->body)) {
            return '<div style="padding: 20px; color: #9ca3af; text-align: center;">La vista previa aparecerá aquí</div>';
        }
        
        return view('components.email-preview', [
            'subject' => $this->subject,
            'body' => $this->body,
            'coupon_code' => $this->coupon_code,
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value,
            'valid_until' => $this->valid_until ? \Carbon\Carbon::parse($this->valid_until)->format('d/m/Y') : null,
        ])->render();
    }
}
