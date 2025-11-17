<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Category; // <-- 1. Importar Category
use App\Models\Restaurant; // <-- 2. Importar Restaurant
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use function __; // Importar la función de traducción

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag'; // <-- Cambié el ícono
    protected static ?string $navigationGroup = 'Gestión del Menú';
    protected static ?int $navigationSort = 2;

    // --- Etiquetas en Español ---
    protected static ?string $modelLabel = 'Producto';
    protected static ?string $pluralModelLabel = 'Productos';
    protected static ?string $navigationLabel = 'Productos';
    // --- Fin Etiquetas ---

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                // Categoría (relación)
                Forms\Components\Select::make('category_id')
                    ->label('Categoría')
                    ->options(Category::all()->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required(),

                // Ocultar y fijar restaurant_id = 1 (restaurante único)
                Forms\Components\Hidden::make('restaurant_id')
                    ->default(1),

                Forms\Components\Textarea::make('description')
                    ->label('Descripción')
                    ->columnSpanFull(),
               
                Forms\Components\TextInput::make('price')
                    ->label('Precio') 
                    ->required()
                    ->numeric()
                    ->prefix('$'),

                Forms\Components\TextInput::make('preparation_time_minutes')
                    ->label('Tiempo de Preparación (minutos)')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->suffix('minutos')
                    ->helperText('Tiempo estimado de preparación del producto en minutos.'),

                Forms\Components\TextInput::make('stock')
                    ->label('Stock')
                    ->numeric()
                    ->default(0)
                    ->helperText('Dejar en 0 para productos fabricados (ej. Pizza). Poner la cantidad para productos de reventa (ej. Coca-Cola).'),

                Forms\Components\TextInput::make('min_stock')
                    ->label('Stock Mínimo')
                    ->numeric()
                    ->default(0)
                    ->helperText('Umbral mínimo de stock. Se mostrará alerta cuando el stock sea menor o igual a este valor.')
                    ->suffix('unidades'),

                Forms\Components\Toggle::make('is_available')
                    ->label('Disponible')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->weight('bold')
                    ->icon(fn ($record) => match($record->category->name ?? '') {
                        'Hamburguesas' => 'heroicon-o-cake',
                        'Papas Fritas' => 'heroicon-o-sparkles',
                        'Bebidas' => 'heroicon-o-beaker',
                        default => 'heroicon-o-shopping-bag'
                    })
                    ->iconColor(fn ($record) => match($record->category->name ?? '') {
                        'Hamburguesas' => 'warning',
                        'Papas Fritas' => 'success',
                        'Bebidas' => 'info',
                        default => 'gray'
                    }),
                
                // --- Columnas de Relaciones ---
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Categoría')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Hamburguesas' => 'warning',
                        'Papas Fritas' => 'success',
                        'Bebidas' => 'info',
                        default => 'gray',
                    }),
                    
                // Ocultar columna restaurant (restaurante único)
                Tables\Columns\TextColumn::make('restaurant.name')
                    ->label('Restaurante')
                    ->sortable()
                    ->searchable()
                    ->hidden(),
                // --- Fin Columnas de Relaciones ---
                
                Tables\Columns\TextColumn::make('price')
                    ->label('Precio') 
                    ->money('ARS')
                    ->sortable(),

                Tables\Columns\TextColumn::make('preparation_time_minutes')
                    ->label('T. Prep. (min)')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->suffix(' min')
                    ->color('info'),

                // real_stock (calculado desde el Accessor)
                Tables\Columns\TextColumn::make('real_stock')
                    ->label('Stock Real')
                    ->sortable()
                    ->color(fn ($record) => ($record->min_stock > 0 && $record->real_stock <= $record->min_stock) ? 'danger' : 'success')
                    ->weight(fn ($record) => ($record->min_stock > 0 && $record->real_stock <= $record->min_stock) ? 'bold' : 'normal')
                    ->suffix(' unidades')
                    ->tooltip('Stock calculado: desde receta o stock directo'),

                Tables\Columns\TextColumn::make('stock')
                    ->label('Stock Directo')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color('gray')
                    ->description('Solo para productos sin receta'),

                Tables\Columns\TextColumn::make('min_stock')
                    ->label('Stock Mínimo')
                    ->sortable()
                    ->badge()
                    ->color('warning')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_available')
                    ->label('Disponible') 
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado el')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(), // <-- Añade filtro para ver borrados (SoftDeletes)
                Tables\Filters\SelectFilter::make('category')
                    ->label('Categoría')
                    ->relationship('category', 'name')
                    ->preload()
                    ->multiple(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(), // <-- Para SoftDeletes
                    Tables\Actions\RestoreBulkAction::make(), // <-- Para SoftDeletes
                ]),
            ])
            ->groups([
                Tables\Grouping\Group::make('category.name')
                    ->label('Categoría')
                    ->collapsible()
                    ->titlePrefixedWithLabel(false),
            ])
            ->defaultGroup('category.name')
            ->defaultSort('price', 'asc')
            ->poll('2s') // Actualización automática cada 2 segundos para ver cambios de stock
            ->deferLoading(); // Mejora la experiencia de carga inicial
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
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'), 
        ];
    }
        // --- AÑADIDO: Soporte para SoftDeletes ---
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