<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DiscountResource\Pages;
use App\Models\Discount;
use App\Models\Restaurant; // Importar
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use function __;

class DiscountResource extends Resource
{
    protected static ?string $model = Discount::class;
    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';
    protected static ?string $navigationGroup = 'Ventas y Finanzas';
    protected static ?int $navigationSort = 4;

    // --- Etiquetas en Español ---
    protected static ?string $modelLabel = 'Descuento';
    protected static ?string $pluralModelLabel = 'Descuentos';
    protected static ?string $navigationLabel = 'Descuentos';
    // --- Fin Etiquetas ---

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Ocultar y fijar restaurant_id = 1
                Forms\Components\Hidden::make('restaurant_id')
                    ->default(1),

                Forms\Components\Section::make('Información del Descuento')
                    ->schema([
                        // Nombre del Descuento
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ej: Descuento 10% Feriado')
                            ->helperText('Nombre descriptivo del descuento')
                            ->columnSpan(1),

                        // Código del Descuento
                        Forms\Components\TextInput::make('code')
                            ->label('Código')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->placeholder('Ej: FERIADO10')
                            ->helperText('Código único para identificar el descuento')
                            ->columnSpan(1),

                        // Tipo de Descuento (con reactividad)
                        Forms\Components\Select::make('type')
                            ->label('Tipo de Descuento')
                            ->options([
                                'percentage' => 'Porcentaje',
                                'fixed' => 'Monto Fijo',
                                'bogo' => '2x1 (Lleva 2 Pagá 1)',
                            ])
                            ->required()
                            ->native(false)
                            ->default('percentage')
                            ->live() // Reactivo para cambiar el sufijo del valor
                            ->afterStateUpdated(function (Forms\Set $set, $state): void {
                                // 2x1 (bogo) no usa `value`: el descuento se calcula
                                // por cantidad de pares. Forzamos value = 0 (columna
                                // NOT NULL) para no romper el guardado.
                                if ($state === 'bogo') {
                                    $set('value', 0);
                                }
                            })
                            ->helperText('Selecciona si el descuento es porcentaje, monto fijo o 2x1')
                            ->columnSpan(1),

                        // Valor del Descuento (cambia según el tipo)
                        Forms\Components\TextInput::make('value')
                            ->label('Valor')
                            ->required(fn (Forms\Get $get): bool => $get('type') !== 'bogo')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(fn (Forms\Get $get): int => 
                                $get('type') === 'percentage' ? 100 : 999999
                            )
                            ->disabled(fn (Forms\Get $get): bool => $get('type') === 'bogo')
                            ->dehydrated()
                            ->suffix(fn (Forms\Get $get): ?string => 
                                $get('type') === 'percentage' ? '%' : ($get('type') === 'bogo' ? '' : '$')
                            )
                            ->step(fn (Forms\Get $get): string => 
                                $get('type') === 'percentage' ? '0.01' : '1'
                            )
                            ->helperText(fn (Forms\Get $get): string => 
                                $get('type') === 'percentage' 
                                    ? 'Ingresa el porcentaje de descuento (0-100%)' 
                                    : ($get('type') === 'bogo' 
                                        ? 'No aplica — el 2x1 se calcula por cantidad de pares del mismo producto' 
                                        : 'Ingresa el monto fijo de descuento en pesos')
                            )
                            ->columnSpan(1),

                        // Estado Activo/Inactivo
                        Forms\Components\Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true)
                            ->helperText('Desactiva si el descuento no está disponible temporalmente')
                            ->columnSpan(1),

                        // Fecha de Expiración
                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Fecha de Expiración')
                            ->nullable()
                            ->native(false)
                            ->seconds(false)
                            ->minDate(now())
                            ->helperText('Fecha y hora en que expira el descuento (debe ser futura)')
                            ->columnSpan(1),

                        // Descripción
                        Forms\Components\Textarea::make('description')
                            ->label('Descripción')
                            ->placeholder('Descripción opcional del descuento...')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),

                // Vigencia horaria (CU-002.2 / OBJ-07): franja de hora + días.
                // Vacío en TODO = elegible siempre.
                Forms\Components\Section::make('Vigencia Horaria (opcional)')
                    ->schema([
                        Forms\Components\TimePicker::make('starts_at')
                            ->label('Inicia a las')
                            ->native(false)
                            ->seconds(false)
                            ->columnSpan(1),

                        Forms\Components\TimePicker::make('ends_at')
                            ->label('Termina a las')
                            ->native(false)
                            ->seconds(false)
                            ->columnSpan(1),

                        Forms\Components\CheckboxList::make('days_of_week')
                            ->label('Días de la semana')
                            ->options([
                                0 => 'Lunes',
                                1 => 'Martes',
                                2 => 'Miércoles',
                                3 => 'Jueves',
                                4 => 'Viernes',
                                5 => 'Sábado',
                                6 => 'Domingo',
                            ])
                            ->columns(4)
                            ->helperText('Vacío = todos los días')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Nombre del Descuento
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                // Código del Descuento
                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                // Valor (formateado según el tipo)
                Tables\Columns\TextColumn::make('value')
                    ->label('Valor')
                    ->formatStateUsing(function (Discount $record, $state): string {
                        return match ($record->type) {
                            'percentage' => $state.'%',
                            'bogo' => '2x1',
                            default => '$'.number_format((float) $state, 2, ',', '.'),
                        };
                    })
                    ->sortable()
                    ->badge()
                    ->color('success'),

                // Tipo de Descuento
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'percentage' => 'Porcentaje',
                        'fixed' => 'Monto Fijo',
                        'bogo' => '2x1',
                        default => ucfirst($state),
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'percentage' => 'warning',
                        'bogo' => 'danger',
                        default => 'info',
                    }),

                // Estado Activo/Inactivo
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->sortable()
                    ->alignCenter(),

                // Fecha de Expiración
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expira')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('Sin expiración')
                    ->description(fn ($record) => $record->expires_at ? $record->expires_at->diffForHumans() : null),

                // Columna oculta (restaurante único)
                Tables\Columns\TextColumn::make('restaurant.name')
                    ->label('Restaurante')
                    ->hidden(),
            ])
            ->filters([
                // Filtro por Tipo
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'percentage' => 'Porcentaje',
                        'fixed' => 'Monto Fijo',
                        'bogo' => '2x1',
                    ]),

                // Filtro por Estado
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activo')
                    ->placeholder('Todos')
                    ->trueLabel('Solo activos')
                    ->falseLabel('Solo inactivos'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->color('primary'),
                Tables\Actions\EditAction::make(),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDiscounts::route('/'),
            'create' => Pages\CreateDiscount::route('/create'),
            'edit' => Pages\EditDiscount::route('/{record}/edit'),
        ];
    }
    
    // Filtrar solo registros del restaurante ID = 1
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('restaurant_id', 1);
    }
}