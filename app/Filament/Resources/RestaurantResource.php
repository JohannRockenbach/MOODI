<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RestaurantResource\Pages;
use App\Filament\Resources\RestaurantResource\RelationManagers;
use App\Models\Restaurant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
// ¡Importante importar SoftDeletingScope si no está!
use Illuminate\Database\Eloquent\SoftDeletingScope; 
use function __; // Importar traductor

class RestaurantResource extends Resource
{
    protected static ?string $model = Restaurant::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront'; // Ícono para restaurantes

    // --- ETIQUETAS EN ESPAÑOL ---
    protected static ?string $modelLabel = 'Restaurante';
    protected static ?string $pluralModelLabel = 'Restaurantes';
    protected static ?string $navigationLabel = 'Restaurantes';
    // --- FIN ETIQUETAS ---
    
public static function form(Form $form): Form
    {
        // ... (Tu método form está PERFECTO, no necesita cambios) ...
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label(__('Name')) // Traducido
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('address')
                    ->label(__('Address')) // Traducido
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('cuit')
                    ->label(__('CUIT')) // Traducido
                    ->required()
                    ->maxLength(20),
                Forms\Components\TextInput::make('contact_phone')
                    ->label(__('Contact Phone')) // Traducido
                    ->maxLength(50),
                Forms\Components\Textarea::make('schedules')
                    ->label(__('Schedules')) // Traducido
                    ->columnSpanFull(),
            ]);
    }

public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // ... (Tus columnas están PERFECTAS) ...
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Name')) // Traducido
                    ->searchable(),
                Tables\Columns\TextColumn::make('address')
                    ->label(__('Address')) // Traducido
                    ->searchable(),
                Tables\Columns\TextColumn::make('cuit')
                    ->label(__('CUIT')) // Traducido
                    ->searchable(),
                Tables\Columns\TextColumn::make('contact_phone')
                    ->label(__('Contact Phone')) // Traducido
                    ->searchable(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(), // Para SoftDeletes
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    // --- CORRECCIÓN 1: Error de Tipeo ---
                    // Antes usaba '.' en lugar de '\'
                    Tables\Actions\ForceDeleteBulkAction::make(), // <-- CORREGIDO
                    Tables\Actions\RestoreBulkAction::make(), // <-- CORREGIDO
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
            'index' => Pages\ListRestaurants::route('/'),
            'create' => Pages\CreateRestaurant::route('/create'),
            'edit' => Pages\EditRestaurant::route('/{record}/edit'),
        ];
    }
    //Añadir esta función para SoftDeletes ---
    // Este método le dice a Filament que incluya los registros "archivados"
    // para que el filtro TrashedFilter pueda encontrarlos.
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}