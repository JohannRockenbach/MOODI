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

    // --- Etiquetas en Español ---
    protected static ?string $modelLabel = 'Ingrediente';
    protected static ?string $pluralModelLabel = 'Ingredientes';
    protected static ?string $navigationLabel = 'Ingredientes';
    // --- Fin Etiquetas ---

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('restaurant_id')
                    ->label(__('Restaurant'))
                    ->options(Restaurant::all()->pluck('name', 'id'))
                    ->searchable()
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('name')
                    ->label(__('Name'))
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('measurement_unit')
                    ->label(__('Measurement Unit'))
                    ->required()
                    ->maxLength(255)
                    ->helperText('Ej: kg, litros, unidades'), // Texto de ayuda
                Forms\Components\TextInput::make('current_stock')
                    ->label(__('Current Stock'))
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('reorder_point')
                    ->label(__('Reorder Point'))
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->helperText('Stock mínimo antes de volver a pedir'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('current_stock')
                    ->label(__('Current Stock'))
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('measurement_unit')
                    ->label(__('Measurement Unit'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('restaurant.name')
                    ->label(__('Restaurant'))
                    ->sortable()
                    ->searchable(),
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
            'index' => Pages\ListIngredients::route('/'),
            'create' => Pages\CreateIngredient::route('/create'),
            'edit' => Pages\EditIngredient::route('/{record}/edit'),
        ];
    }    
    
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}