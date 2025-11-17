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
                            ])
                            ->required()
                            ->native(false)
                            ->default('percentage')
                            ->live() // Reactivo para cambiar el sufijo del valor
                            ->helperText('Selecciona si el descuento es un porcentaje o un monto fijo')
                            ->columnSpan(1),

                        // Valor del Descuento (cambia según el tipo)
                        Forms\Components\TextInput::make('value')
                            ->label('Valor')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(fn (Forms\Get $get): int => 
                                $get('type') === 'percentage' ? 100 : 999999
                            )
                            ->suffix(fn (Forms\Get $get): ?string => 
                                $get('type') === 'percentage' ? '%' : '$'
                            )
                            ->step(fn (Forms\Get $get): string => 
                                $get('type') === 'percentage' ? '0.01' : '1'
                            )
                            ->helperText(fn (Forms\Get $get): string => 
                                $get('type') === 'percentage' 
                                    ? 'Ingresa el porcentaje de descuento (0-100%)' 
                                    : 'Ingresa el monto fijo de descuento en pesos'
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
                        return $record->type === 'percentage' ? $state . '%' : '$' . number_format((float) $state, 2, ',', '.');
                    })
                    ->sortable()
                    ->badge()
                    ->color('success'),

                // Tipo de Descuento
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (string $state): string => $state === 'percentage' ? 'Porcentaje' : 'Monto Fijo')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'percentage' ? 'warning' : 'info'),

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