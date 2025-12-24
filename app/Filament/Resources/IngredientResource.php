<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IngredientResource\Pages;
use App\Filament\Resources\IngredientResource\RelationManagers;
use App\Models\Ingredient;
use App\Models\Restaurant; // <-- Importante
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope; // <-- Importante
use function __; // Importar traductor
class IngredientResource extends Resource
{
    
    protected static ?string $model = Ingredient::class;

    protected static ?string $navigationIcon = 'heroicon-o-beaker'; // Ícono de probeta
    protected static ?string $navigationGroup = 'Inventario y Compras';
    protected static ?int $navigationSort = 1;


    // --- Etiquetas en Español ---
    protected static ?string $modelLabel = 'Ingrediente';
    protected static ?string $pluralModelLabel = 'Ingredientes';
    protected static ?string $navigationLabel = 'Ingredientes';
    // --- Fin Etiquetas ---

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Ocultar y fijar restaurant_id = 1 (restaurante único)
                Forms\Components\Select::make('restaurant_id')
                    ->hidden()
                    ->default(1),
                Forms\Components\TextInput::make('name')
                    ->label(__('Name'))
                    ->required()
                    ->maxLength(255),
                
                // Unidad de medida por tipo (sólidos, líquidos, etc.)
                Forms\Components\Select::make('measurement_unit')
                    ->label('Unidad de Medida')
                    ->required()
                    ->searchable()
                    ->options([
                        // Sólidos
                        'kg' => 'Kilogramos (kg)',
                        'g' => 'Gramos (g)',
                        'mg' => 'Miligramos (mg)',
                        'lb' => 'Libras (lb)',
                        'oz' => 'Onzas (oz)',
                        // Líquidos
                        'l' => 'Litros (l)',
                        'ml' => 'Mililitros (ml)',
                        'gal' => 'Galones (gal)',
                        'pt' => 'Pintas (pt)',
                        // Unidades
                        'unidad' => 'Unidades',
                        'docena' => 'Docenas',
                        'caja' => 'Cajas',
                        'paquete' => 'Paquetes',
                        'bolsa' => 'Bolsas',
                    ])
                    ->helperText('Selecciona la unidad de medida según el tipo de ingrediente'),
                
                Forms\Components\TextInput::make('min_stock')
                    ->label('Stock Mínimo')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->helperText('Umbral mínimo de stock. Se mostrará alerta cuando el stock sea menor o igual a este valor.'),
                Forms\Components\TextInput::make('reorder_point')
                    ->label('Punto de Reorden')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->helperText('Stock mínimo antes de volver a pedir'),

                // RELACIÓN MUCHOS-A-MUCHOS: Proveedores
                // Nota: Los campos pivot (precio y unidad de compra) se gestionan desde el ProviderResource
                Forms\Components\Select::make('providers')
                    ->label('Proveedores')
                    ->relationship(
                        'providers', 
                        'business_name',
                        fn (Builder $query) => $query->where('restaurant_id', 1)
                    )
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->helperText('Selecciona los proveedores. Para configurar precios, edita desde Proveedores.')
                    ->columnSpanFull(),

                // SECCIÓN: Lote Inicial (solo visible al crear)
                Forms\Components\Section::make('Lote Inicial')
                    ->description('Agrega el primer lote de este ingrediente con su stock inicial.')
                    ->schema([
                        Forms\Components\TextInput::make('initial_quantity')
                            ->label('Cantidad Inicial')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->suffix(fn (Forms\Get $get): ?string => $get('measurement_unit'))
                            ->helperText(fn (Forms\Get $get): string => 
                                $get('measurement_unit') 
                                    ? 'Cantidad en ' . $get('measurement_unit')
                                    : 'Primero selecciona la unidad de medida'
                            )
                            ->live()
                            ->dehydrated(false), // No guardar en tabla ingredients
                        
                        Forms\Components\DatePicker::make('initial_purchase_date')
                            ->label('Fecha de Compra')
                            ->default(now())
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->required()
                            ->dehydrated(false), // No guardar en tabla ingredients
                        
                        Forms\Components\DatePicker::make('initial_expiration_date')
                            ->label('Fecha de Vencimiento')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->nullable()
                            ->helperText('Dejar vacío si el ingrediente no tiene vencimiento')
                            ->dehydrated(false), // No guardar en tabla ingredients
                    ])
                    ->columns(2)
                    ->visibleOn('create'), // Solo visible al crear
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('total_stock')
                    ->label('Stock Actual')
                    ->state(fn (\App\Models\Ingredient $record): float => $record->batches()->sum('quantity'))
                    ->numeric()
                    ->sortable()
                    ->color(fn ($record) => $record->batches()->sum('quantity') <= $record->min_stock ? 'danger' : 'success')
                    ->weight(fn ($record) => $record->batches()->sum('quantity') <= $record->min_stock ? 'bold' : 'normal'),
                Tables\Columns\TextColumn::make('min_stock')
                    ->label('Stock Mínimo')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('warning')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('measurement_unit')
                    ->label('Unidad de Medida')
                    ->badge()
                    ->color('info')
                    ->searchable(),
                // Ocultar columna restaurant (restaurante único)
                Tables\Columns\TextColumn::make('restaurant.name')
                    ->label(__('Restaurant'))
                    ->sortable()
                    ->searchable()
                    ->hidden(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(), // Soporte para SoftDeletes
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->poll('3s') // Actualización automática cada 3 segundos para ver cambios de stock
            ->deferLoading(); // Mejora la experiencia de carga inicial
    }
    
    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\IngredientResource\RelationManagers\BatchesRelationManager::class,
        ];
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIngredients::route('/'),
            'create' => Pages\CreateIngredient::route('/create'),
            'edit' => Pages\EditIngredient::route('/{record}/edit'),
        ];
    }    
    
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            // Filtrar solo registros del restaurante ID = 1
            ->where('restaurant_id', 1)
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}