<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\Table as TableModel;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use function __;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    // Use a heroicon that is available in the project's icon set to avoid Blade Icons errors
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Operaciones del Salón';
    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Pedido';
    protected static ?string $pluralModelLabel = 'Pedidos';
    protected static ?string $navigationLabel = 'Pedidos';

    // Optimización N+1: Eager loading de relaciones
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            // Filtrar solo registros del restaurante ID = 1
            ->where('restaurant_id', 1)
            ->with(['user', 'table', 'orderProducts.product', 'customer']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            // Ocultar y fijar restaurant_id = 1 (Sistema de restaurante único)
            Forms\Components\Hidden::make('restaurant_id')
                ->default(1),

            Forms\Components\Section::make('Información del Pedido')
                ->schema([
                    // Cliente (Opcional - Relación con Clientes)
                    Forms\Components\Select::make('customer_id')
                        ->label('Cliente (Opcional)')
                        ->relationship('customer', 'name')
                        ->searchable()
                        ->preload()
                        ->helperText('Selecciona un cliente existente o crea uno nuevo')
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')
                                ->label('Nombre')
                                ->required()
                                ->maxLength(255),
                            
                            Forms\Components\TextInput::make('email')
                                ->label('Email')
                                ->email()
                                ->maxLength(255),
                            
                            Forms\Components\TextInput::make('phone')
                                ->label('Teléfono')
                                ->tel()
                                ->maxLength(255),
                        ])
                        ->columnSpan(1),

                    // ==========================================
                    // ESTRATEGIA: Cuando viene del mapa, NO mostrar Select editables
                    // Usamos propiedades de Livewire que persisten durante toda la sesión
                    // ==========================================
                    
                    // TIPO: Select editable (solo cuando NO viene del mapa)
                    Forms\Components\Select::make('type')
                        ->label('Tipo de Pedido')
                        ->options([
                            'salon' => 'Salón',
                            'delivery' => 'Delivery',
                            'para_llevar' => 'Para Llevar',
                        ])
                        ->default('salon')
                        ->required()
                        ->live(onBlur: false, debounce: 0)
                        ->native(false)
                        ->visible(function ($operation, $livewire) {
                            // Solo visible si NO es create O NO viene del mapa
                            if ($operation === 'create' && method_exists($livewire, 'isFromTableMap')) {
                                return !$livewire->isFromTableMap();
                            }
                            return $operation !== 'create'; // En edit, siempre visible
                        })
                        ->helperText('Selecciona el tipo de pedido')
                        ->columnSpan(1),
                    
                    // TIPO: Campo bloqueado visual (solo cuando viene del mapa en CREATE)
                    Forms\Components\Placeholder::make('type_locked_display')
                        ->label('Tipo de Pedido')
                        ->content(function () {
                            return '🔒 Salón (Mesa seleccionada desde el mapa)';
                        })
                        ->visible(function ($operation, $livewire) {
                            if ($operation === 'create' && method_exists($livewire, 'isFromTableMap')) {
                                return $livewire->isFromTableMap();
                            }
                            return false;
                        })
                        ->columnSpan(1),
                    
                    // TIPO: Hidden field para enviar el valor al backend (solo en CREATE desde mapa)
                    Forms\Components\Hidden::make('_locked_type')
                        ->default('salon')
                        ->visible(function ($operation, $livewire) {
                            if ($operation === 'create' && method_exists($livewire, 'isFromTableMap')) {
                                return $livewire->isFromTableMap();
                            }
                            return false;
                        }),

                    // MESA: Select editable (solo cuando es salón Y NO viene del mapa)
                    Forms\Components\Select::make('table_id')
                        ->label('Mesa')
                        ->relationship('table', 'number')
                        ->options(fn() => TableModel::where('restaurant_id', 1)->pluck('number', 'id'))
                        ->searchable()
                        ->required(fn (Forms\Get $get): bool => ($get('type') ?? $get('_locked_type')) === 'salon')
                        ->visible(function (Forms\Get $get, $operation, $livewire): bool {
                            $type = $get('type') ?? $get('_locked_type');
                            
                            // Si no es salón, no mostrar
                            if ($type !== 'salon') {
                                return false;
                            }
                            
                            // En create o edit, solo mostrar si NO viene del mapa
                            if (method_exists($livewire, 'isFromTableMap')) {
                                return !$livewire->isFromTableMap();
                            }
                            
                            // Por defecto visible
                            return true;
                        })
                        ->helperText('Selecciona la mesa donde se realiza el pedido')
                        ->columnSpan(1),
                    
                    // MESA: Campo bloqueado visual (cuando viene del mapa en CREATE o EDIT)
                    Forms\Components\Placeholder::make('table_locked_display')
                        ->label('Mesa')
                        ->content(function ($livewire) {
                            if (method_exists($livewire, 'getLockedTableId')) {
                                $tableId = $livewire->getLockedTableId();
                                if ($tableId) {
                                    $table = TableModel::find($tableId);
                                    if ($table) {
                                        return "🔒 Mesa #{$table->number} - {$table->location}";
                                    }
                                    return "🔒 Mesa #{$tableId}";
                                }
                            }
                            return 'N/A';
                        })
                        ->visible(function ($operation, $livewire) {
                            // Visible en CREATE o EDIT cuando viene del mapa
                            if (method_exists($livewire, 'isFromTableMap')) {
                                return $livewire->isFromTableMap();
                            }
                            return false;
                        })
                        ->columnSpan(1),
                    
                    // MESA: Hidden field con nombre único para enviar al backend
                    Forms\Components\Hidden::make('_locked_table_id')
                        ->default(function ($livewire) {
                            if (method_exists($livewire, 'getLockedTableId')) {
                                return $livewire->getLockedTableId();
                            }
                            return null;
                        })
                        ->visible(function ($operation, $livewire) {
                            if ($operation === 'create' && method_exists($livewire, 'isFromTableMap')) {
                                return $livewire->isFromTableMap();
                            }
                            return false;
                        }),

                    // Dirección de Delivery (VISIBLE SOLO SI ES DELIVERY)
                    Forms\Components\TextInput::make('delivery_address')
                        ->label('Dirección de Entrega')
                        ->placeholder('Ej: Calle 123, Barrio X')
                        ->required(fn (Forms\Get $get): bool => 
                            ($get('type') ?? $get('_locked_type')) === 'delivery'
                        )
                        ->visible(fn (Forms\Get $get): bool => 
                            ($get('type') ?? $get('_locked_type')) === 'delivery'
                        )
                        ->helperText('Dirección completa para el delivery')
                        ->columnSpan(1),

                    // Teléfono de Delivery (VISIBLE SOLO SI ES DELIVERY)
                    Forms\Components\TextInput::make('delivery_phone')
                        ->label('Teléfono')
                        ->placeholder('Ej: 0351-1234567')
                        ->tel()
                        ->required(fn (Forms\Get $get): bool => 
                            ($get('type') ?? $get('_locked_type')) === 'delivery'
                        )
                        ->visible(fn (Forms\Get $get): bool => 
                            ($get('type') ?? $get('_locked_type')) === 'delivery'
                        )
                        ->helperText('Teléfono de contacto para el delivery')
                        ->columnSpan(1),

                    // Nombre del Cliente (VISIBLE SOLO SI ES PARA LLEVAR O DELIVERY)
                    Forms\Components\TextInput::make('customer_name')
                        ->label('Nombre del Cliente')
                        ->placeholder('Ej: Juan Pérez')
                        ->required(fn (Forms\Get $get): bool => 
                            in_array(($get('type') ?? $get('_locked_type')), ['delivery', 'para_llevar'])
                        )
                        ->visible(fn (Forms\Get $get): bool => 
                            in_array(($get('type') ?? $get('_locked_type')), ['delivery', 'para_llevar'])
                        )
                        ->helperText('Nombre de quien retira/recibe el pedido')
                        ->columnSpan(1),

                    // Mozo (Usuario)
                    Forms\Components\Select::make('waiter_id')
                        ->label('Mozo')
                        ->relationship('user', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->afterStateHydrated(function ($state, $set) {
                            if (Auth::check() && !$state) {
                                $set('waiter_id', Auth::id());
                            }
                        })
                        ->disabled(fn ($operation) => $operation === 'create' && Auth::check())
                        ->dehydrated()
                        ->helperText(fn ($operation) => $operation === 'create' && Auth::check() ? '🔒 Auto-asignado: ' . Auth::user()->name : 'Mozo que tomó el pedido')
                        ->columnSpan(1),

                    // Estado del Pedido
                    Forms\Components\Select::make('status')
                        ->label('Estado')
                        ->options([
                            'pending' => 'Pendiente',
                            'processing' => 'En Proceso',
                            'completed' => 'Completado',
                            'cancelled' => 'Cancelado',
                        ])
                        ->default('pending')
                        ->required()
                        ->native(false)
                        ->columnSpan(1),

                    // Notas del Pedido
                    Forms\Components\Textarea::make('notes')
                        ->label('Notas')
                        ->placeholder('Instrucciones especiales para el pedido...')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->collapsible(),

            // PRODUCTOS DEL PEDIDO
            Forms\Components\Section::make('Productos del Pedido')
                ->schema([
                    Forms\Components\Repeater::make('orderProducts')
                        ->relationship('orderProducts')
                        ->label('')
                        ->live() // IMPORTANTE: Actualiza el total en tiempo real
                        ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                            // Calcular total automáticamente
                            $total = 0;
                            $orderProducts = $get('orderProducts') ?? [];
                            
                            foreach ($orderProducts as $item) {
                                if (isset($item['price']) && isset($item['quantity'])) {
                                    $total += (float)$item['price'] * (int)$item['quantity'];
                                }
                            }
                            
                            $set('total_display', number_format($total, 2, ',', '.'));
                        })
                        ->schema([
                            // SELECTOR DE CATEGORÍA (VIRTUAL - SOLO PARA FILTRAR)
                            Forms\Components\Select::make('category_id')
                                ->label('Categoría')
                                ->options([
                                    '🍔 HAMBURGUESAS' => '🍔 Hamburguesas',
                                    '🥤 BEBIDAS' => '🥤 Bebidas',
                                    '🍟 PAPAS FRITAS' => '🍟 Papas Fritas',
                                ])
                                ->placeholder('Seleccionar categoría...')
                                ->live() // Reactivo para actualizar el producto
                                ->dehydrated(false) // NO GUARDAR en la base de datos
                                ->afterStateUpdated(fn (Forms\Set $set) => $set('product_id', null)) // RESET del producto (FIX BUG)
                                ->native(false)
                                ->columnSpan(1),

                            // SELECTOR DE PRODUCTO (FILTRADO POR CATEGORÍA)
                            Forms\Components\Select::make('product_id')
                                ->label('Producto')
                                ->options(function (Forms\Get $get) {
                                    $categoryFilter = $get('category_id');
                                    
                                    $products = \App\Models\Product::where('restaurant_id', 1)
                                        ->where('is_available', true)
                                        ->with('category')
                                        ->get();
                                    
                                    // Si hay filtro de categoría, filtrar productos
                                    if ($categoryFilter) {
                                        $products = $products->filter(function($product) use ($categoryFilter) {
                                            $categoryName = strtolower($product->category?->name ?? '');
                                            
                                            return match($categoryFilter) {
                                                '🍔 HAMBURGUESAS' => str_contains($categoryName, 'hamburgues'),
                                                '🥤 BEBIDAS' => str_contains($categoryName, 'bebida'),
                                                '🍟 PAPAS FRITAS' => str_contains($categoryName, 'papa') || str_contains($categoryName, 'frita'),
                                                default => false,
                                            };
                                        });
                                    }
                                    
                                    return $products->pluck('name', 'id')->toArray();
                                })
                                ->searchable()
                                ->required()
                                ->live() // Reactivo para actualizar precio
                                ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                                    if ($state) {
                                        $product = \App\Models\Product::find($state);
                                        $set('price', $product?->price ?? 0);
                                    }
                                })
                                ->native(false)
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('quantity')
                                ->label('Cantidad')
                                ->numeric()
                                ->required()
                                ->default(1)
                                ->minValue(1)
                                ->maxValue(999)
                                ->live(onBlur: true) // Actualiza el total al cambiar
                                ->columnSpan(1),

                            Forms\Components\TextInput::make('price')
                                ->label('Precio')
                                ->numeric()
                                ->required()
                                ->readOnly()
                                ->prefix('$')
                                ->columnSpan(1),

                            Forms\Components\Textarea::make('notes')
                                ->label('Notas')
                                ->placeholder('Instrucciones especiales...')
                                ->rows(2)
                                ->columnSpanFull(),
                        ])
                        ->columns(5) // Cambié a 5 columnas para incluir la categoría
                        ->defaultItems(0)
                        ->addActionLabel('➕ Agregar Producto')
                        ->reorderableWithButtons()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => 
                            \App\Models\Product::find($state['product_id'] ?? null)?->name ?? 'Nuevo Producto'
                        )
                        ->columnSpanFull(),
                ])
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'processing' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pendiente',
                        'processing' => 'En Proceso',
                        'completed' => 'Completado',
                        'cancelled' => 'Cancelado',
                        default => ucfirst($state),
                    })
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->sortable()
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'salon' => 'Salón',
                        'delivery' => 'Delivery',
                        'para_llevar' => 'Para Llevar',
                        default => ucfirst($state),
                    }),
                
                Tables\Columns\TextColumn::make('table.number')
                    ->label('Mesa')
                    ->sortable()
                    ->badge()
                    ->color('primary')
                    ->default('N/A'),
                
                Tables\Columns\TextColumn::make('waiter.name')
                    ->label('Mozo')
                    ->sortable()
                    ->searchable()
                    ->default('N/A'),
                
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->state(function (Order $record): float {
                        // Asegurarse de que orderProducts esté cargado
                        if (!$record->relationLoaded('orderProducts')) {
                            $record->load('orderProducts');
                        }
                        
                        return $record->orderProducts->sum(function ($orderProduct) {
                            return (float)$orderProduct->quantity * (float)$orderProduct->price;
                        });
                    })
                    ->formatStateUsing(function ($state) {
                        return '$ ' . number_format((float) $state, 2, ',', '.');
                    })
                    ->sortable()
                    ->weight('bold')
                    ->color('success')
                    ->size('lg'),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->poll('2s') // Actualización automática cada 2 segundos
            ->deferLoading()
            ->filters([
                // Filtro por Tipo
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'salon' => 'Salón',
                        'delivery' => 'Delivery',
                        'para_llevar' => 'Para Llevar',
                    ]),
                
                // Filtro por Mesa
                Tables\Filters\SelectFilter::make('table_id')
                    ->label('Mesa')
                    ->relationship('table', 'number'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                
                // Acciones rápidas de estado
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('toProcessing')
                        ->label('Marcar En Proceso')
                        ->icon('heroicon-o-arrow-path')
                        ->color('info')
                        ->hidden(fn (Order $record): bool => in_array($record->status, ['processing', 'completed', 'cancelled']))
                        ->requiresConfirmation()
                        ->modalHeading('Iniciar Procesamiento')
                        ->modalDescription('El stock se descontará automáticamente. Esta acción no se puede revertir.')
                        ->action(function (Order $record): void {
                            $record->status = 'processing';
                            $record->save();
                        }),
                    
                    Tables\Actions\Action::make('toCompleted')
                        ->label('Marcar Completado')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->hidden(fn (Order $record): bool => in_array($record->status, ['completed', 'cancelled']))
                        ->requiresConfirmation()
                        ->modalHeading('Completar Pedido')
                        ->modalDescription('¿El pedido fue entregado/servido al cliente?')
                        ->action(function (Order $record): void {
                            $record->status = 'completed';
                            $record->save();
                        }),
                    
                    Tables\Actions\Action::make('toCancelled')
                        ->label('Cancelar Pedido')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->hidden(fn (Order $record): bool => in_array($record->status, ['completed', 'cancelled']))
                        ->requiresConfirmation()
                        ->modalHeading('Cancelar Pedido')
                        ->modalDescription(function (Order $record): string {
                            if ($record->status === 'pending') {
                                return '¿Estás seguro de cancelar este pedido? No se han usado ingredientes aún.';
                            }
                            return '⚠️ ATENCIÓN: Este pedido ya está en preparación. Los ingredientes utilizados se registrarán como merma (desperdicio). ¿Confirmas la cancelación?';
                        })
                        ->modalSubmitActionLabel('Sí, cancelar')
                        ->modalIcon('heroicon-o-exclamation-triangle')
                        ->action(function (Order $record): void {
                            $previousStatus = $record->status;
                            $record->status = 'cancelled';
                            $record->save();
                            
                            if ($previousStatus === 'pending') {
                                \Filament\Notifications\Notification::make()
                                    ->success()
                                    ->title('Pedido Cancelado')
                                    ->body("El pedido #{$record->id} ha sido cancelado correctamente.")
                                    ->icon('heroicon-o-check-circle')
                                    ->duration(5000)
                                    ->send();
                            } else {
                                \Filament\Notifications\Notification::make()
                                    ->warning()
                                    ->title('Pedido Cancelado')
                                    ->body("El pedido #{$record->id} fue cancelado. Los ingredientes ya usados se registran como merma.")
                                    ->icon('heroicon-o-exclamation-triangle')
                                    ->duration(7000)
                                    ->send();
                            }
                        }),
                ])
                ->label('Estado')
                ->icon('heroicon-m-ellipsis-vertical')
                ->size('sm')
                ->color('primary')
                ->button(),
                
                // Acción para registrar pago y crear venta
                Tables\Actions\Action::make('registerPayment')
                    ->label('Cobrar')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('success')
                    ->hidden(fn (Order $record): bool => 
                        $record->status === 'cancelled' || // No cobrar pedidos cancelados
                        $record->sale()->exists() // No cobrar si ya tiene una venta registrada
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Registrar Pago')
                    ->modalDescription('Se creará una venta asociada a este pedido. El estado del pedido no cambiará.')
                    ->modalSubmitActionLabel('Confirmar Pago')
                    ->modalWidth('lg')
                    ->form(function (Order $record): array {
                        // Calcular el total del pedido
                        $orderTotal = $record->orderProducts->sum(function ($orderProduct) {
                            return $orderProduct->quantity * $orderProduct->price;
                        });
                        
                        return [
                            Forms\Components\Section::make('Información del Pago')
                                ->schema([
                                    Forms\Components\Select::make('payment_method')
                                        ->label('Método de Pago')
                                        ->options([
                                            'cash' => 'Efectivo',
                                            'card' => 'Tarjeta',
                                            'transfer' => 'Transferencia',
                                        ])
                                        ->required()
                                        ->native(false)
                                        ->columnSpanFull(),
                                    
                                    Forms\Components\Placeholder::make('order_total')
                                        ->label('Total del Pedido')
                                        ->content(fn () => '$ ' . number_format($orderTotal, 2, ',', '.')),
                                    
                                    Forms\Components\Select::make('discount_ids')
                                        ->label('Aplicar Descuentos')
                                        ->multiple()
                                        ->options(\App\Models\Discount::where('is_active', true)
                                            ->whereNotNull('name')
                                            ->pluck('name', 'id'))
                                        ->preload()
                                        ->searchable()
                                        ->live()
                                        ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get) use ($orderTotal) {
                                            $discountIds = $get('discount_ids') ?? [];
                                            $totalDiscount = 0;
                                            
                                            if (!empty($discountIds)) {
                                                $discounts = \App\Models\Discount::whereIn('id', $discountIds)->get();
                                                
                                                foreach ($discounts as $discount) {
                                                    if ($discount->type === 'percentage') {
                                                        $totalDiscount += $orderTotal * ($discount->value / 100);
                                                    } else { // 'fixed'
                                                        $totalDiscount += $discount->value;
                                                    }
                                                }
                                            }
                                            
                                            $finalTotal = max(0, $orderTotal - $totalDiscount);
                                            $set('total_amount', $finalTotal);
                                            $set('discount_amount', $totalDiscount);
                                        })
                                        ->helperText('Puedes seleccionar múltiples descuentos'),
                                    
                                    Forms\Components\Placeholder::make('discount_display')
                                        ->label('Descuento Aplicado')
                                        ->content(function (Forms\Get $get) {
                                            $discountAmount = $get('discount_amount') ?? 0;
                                            if ($discountAmount > 0) {
                                                return '- $ ' . number_format($discountAmount, 2, ',', '.');
                                            }
                                            return '$ 0,00';
                                        })
                                        ->visible(fn (Forms\Get $get) => !empty($get('discount_ids'))),
                                    
                                    Forms\Components\Hidden::make('discount_amount')
                                        ->default(0),
                                    
                                    Forms\Components\TextInput::make('total_amount')
                                        ->label('Total a Cobrar')
                                        ->prefix('$')
                                        ->numeric()
                                        ->default($orderTotal)
                                        ->readOnly()
                                        ->extraAttributes(['class' => 'font-bold text-lg'])
                                        ->helperText('Total con descuentos aplicados'),
                                ])
                                ->columns(2),
                        ];
                    })
                    ->action(function (Order $record, array $data): void {
                        // Buscar la caja abierta
                        $caja = \App\Models\Caja::where('restaurant_id', 1)
                            ->where('status', 'abierta')
                            ->first();
                        
                        // Validar que exista una caja abierta
                        if (!$caja) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Error: No hay Caja Abierta')
                                ->body('Debes abrir una caja antes de registrar pagos. Ve a la sección de Cajas.')
                                ->persistent()
                                ->send();
                            return;
                        }
                        
                        try {
                            \Illuminate\Support\Facades\DB::transaction(function () use ($record, $data, $caja) {
                                // Recalcular el total en el servidor por seguridad
                                $orderTotal = $record->orderProducts->sum(function ($orderProduct) {
                                    return $orderProduct->quantity * $orderProduct->price;
                                });
                                
                                $totalDiscount = 0;
                                $discountIds = $data['discount_ids'] ?? [];
                                $discountAmounts = []; // Para guardar el monto de cada descuento
                                
                                if (!empty($discountIds)) {
                                    $discounts = \App\Models\Discount::whereIn('id', $discountIds)->get();
                                    
                                    foreach ($discounts as $discount) {
                                        $discountAmount = 0;
                                        
                                        if ($discount->type === 'percentage') {
                                            $discountAmount = $orderTotal * ($discount->value / 100);
                                        } else { // 'fixed'
                                            $discountAmount = $discount->value;
                                        }
                                        
                                        $totalDiscount += $discountAmount;
                                        $discountAmounts[$discount->id] = $discountAmount;
                                    }
                                }
                                
                                $finalTotal = max(0, $orderTotal - $totalDiscount);
                                
                                // Crear la venta
                                $sale = \App\Models\Sale::create([
                                    'restaurant_id' => 1,
                                    'caja_id' => $caja->id,
                                    'order_id' => $record->id,
                                    'total_amount' => $finalTotal,
                                    'payment_method' => $data['payment_method'],
                                    'status' => 'paid',
                                ]);
                                
                                // Asociar los descuentos a la venta con sus montos individuales
                                if (!empty($discountIds)) {
                                    foreach ($discountAmounts as $discountId => $amount) {
                                        $sale->discounts()->attach($discountId, [
                                            'amount_discounted' => $amount
                                        ]);
                                    }
                                }
                                
                                // El estado del pedido NO se modifica aquí
                                // Se maneja independientemente con los botones de estado
                            });
                            
                            // Notificación de éxito
                            $discountText = !empty($data['discount_ids']) 
                                ? ' (con descuento aplicado)' 
                                : '';
                            
                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Pago Registrado')
                                ->body("El pago del pedido #{$record->id} fue registrado correctamente{$discountText}. Total: $ " . number_format($data['total_amount'], 2, ',', '.'))
                                ->icon('heroicon-o-check-circle')
                                ->duration(5000)
                                ->send();
                                
                        } catch (\Throwable $e) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Error al Registrar Pago')
                                ->body('Ocurrió un error: ' . $e->getMessage())
                                ->persistent()
                                ->send();
                        }
                    }),
                
                // Botón para crear un NUEVO pedido con las mismas características
                Tables\Actions\Action::make('addProduct')
                    ->label('Nuevo Pedido')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('product_id')
                            ->label('Producto')
                            ->options(function () {
                                return \App\Models\Product::where('restaurant_id', 1)
                                    ->where('is_available', true)
                                    ->with('category')
                                    ->get()
                                    ->groupBy('category.name')
                                    ->map(function ($products, $category) {
                                        return $products->pluck('name', 'id');
                                    });
                            })
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                                if ($state) {
                                    $product = \App\Models\Product::find($state);
                                    $set('price', $product?->price ?? 0);
                                }
                            }),
                        
                        Forms\Components\TextInput::make('quantity')
                            ->label('Cantidad')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->minValue(1)
                            ->suffix('unid.'),
                        
                        Forms\Components\TextInput::make('price')
                            ->label('Precio Unitario')
                            ->numeric()
                            ->required()
                            ->readOnly()
                            ->prefix('$'),
                        
                        Forms\Components\Textarea::make('notes')
                            ->label('Notas del Producto')
                            ->placeholder('Ej: Sin cebolla, bien cocido...')
                            ->rows(2),
                    ])
                    ->action(function (Order $record, array $data): void {
                        // Crear un NUEVO pedido con las mismas características del original
                        $newOrder = Order::create([
                            'restaurant_id' => $record->restaurant_id,
                            'type' => $record->type,
                            'table_id' => $record->table_id,
                            'waiter_id' => $record->waiter_id,
                            'delivery_address' => $record->delivery_address,
                            'delivery_phone' => $record->delivery_phone,
                            'customer_name' => $record->customer_name,
                            'status' => 'pending', // NUEVO pedido siempre empieza en pendiente
                            'stock_deducted' => false,
                            'notes' => 'Pedido adicional', // Opcional: marcar que es adicional
                        ]);
                        
                        // Agregar el producto al nuevo pedido
                        $newOrder->orderProducts()->create([
                            'product_id' => $data['product_id'],
                            'quantity' => $data['quantity'],
                            'price' => $data['price'],
                            'notes' => $data['notes'] ?? null,
                        ]);
                        
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Nuevo pedido creado')
                            ->body(function () use ($newOrder, $record) {
                                $tableInfo = $record->table_id ? 'Mesa ' . optional($record->getRelation('table'))->number : $record->customer_name;
                                return 'Se creó el pedido #' . $newOrder->id . ' para ' . $tableInfo;
                            })
                            ->send();
                    })
                    ->modalHeading(function (Order $record) {
                        $tableInfo = $record->table_id ? 'Mesa ' . optional($record->getRelation('table'))->number : $record->customer_name;
                        return 'Nuevo Pedido - ' . $tableInfo;
                    })
                    ->modalDescription('Se creará un nuevo pedido independiente con las mismas características (mesa, mozo, tipo) pero en estado "Pendiente".')
                    ->modalSubmitActionLabel('Crear Pedido')
                    ->modalWidth('lg'),
                
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\OrderProductsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
            // Página de Cocina / KDS
            'kitchen' => Pages\KitchenDashboard::route('/kitchen'),
        ];
    }
}
