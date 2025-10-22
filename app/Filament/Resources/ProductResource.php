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
                    ->label(__('Name')) // Traducido
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(), // Ocupa todo el ancho

                // --- ¡AQUÍ LA MAGIA DE LAS RELACIONES! ---
                Forms\Components\Select::make('category_id')
                    ->label(__('Category')) // Traducido
                    ->options(Category::all()->pluck('name', 'id')) // Carga Categorías
                    ->searchable() // Habilita la búsqueda
                    ->required(),

                Forms\Components\Select::make('restaurant_id')
                    ->label(__('Restaurant')) // Traducido
                    ->options(Restaurant::all()->pluck('name', 'id')) // Carga Restaurantes
                    ->searchable()
                    ->required(),
                // --- FIN RELACIONES ---

                Forms\Components\Textarea::make('description')
                    ->label(__('Description')) // Traducido
                    ->columnSpanFull(),
               
                Forms\Components\TextInput::make('price')
                    ->label(__('Price')) 
                    ->required()
                    ->numeric()
                    ->prefix('$'),

                Forms\Components\TextInput::make('stock')
                    ->label(__('Stock'))
                    ->numeric()
                    ->default(0)
                    ->helperText('Dejar en 0 para productos fabricados (ej. Pizza). Poner la cantidad para productos de reventa (ej. Coca-Cola).'),

                Forms\Components\Toggle::make('is_available')
                    ->label(__('Available')) 
                    ->required(),

                Forms\Components\Toggle::make('is_available')
                    ->label(__('Available')) // Traducido
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Name')) // Traducido
                    ->searchable(),
                
                // --- Columnas de Relaciones ---
                Tables\Columns\TextColumn::make('category.name')
                    ->label(__('Category')) // Traducido
                    ->sortable()
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('restaurant.name')
                    ->label(__('Restaurant')) // Traducido
                    ->sortable()
                    ->searchable(),
                // --- Fin Columnas de Relaciones ---
                
                Tables\Columns\TextColumn::make('price')
                    ->label(__('Price')) 
                    ->money('ARS')
                    ->sortable(),


                Tables\Columns\TextColumn::make('stock')
                    ->label(__('Stock'))
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_available')
                    ->label(__('Available')) 
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_available')
                    ->label(__('Available')) // Traducido
                    ->boolean(), // Muestra un ícono de check o X

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created at')) // Traducido
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(), // <-- Añade filtro para ver borrados (SoftDeletes)
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
            ]);
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
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}