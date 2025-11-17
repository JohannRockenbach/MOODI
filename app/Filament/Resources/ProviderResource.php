<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProviderResource\Pages;
use App\Filament\Resources\ProviderResource\RelationManagers;
use App\Models\Provider;
use App\Models\Restaurant; // <-- Importante
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope; // <-- Importante
use function __; // Importar traductor

class ProviderResource extends Resource
{
    protected static ?string $model = Provider::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck'; // Ícono de camión
    protected static ?string $navigationGroup = 'Inventario y Compras';
    protected static ?int $navigationSort = 2;


    // --- Etiquetas en Español ---
    protected static ?string $modelLabel = 'Proveedor';
    protected static ?string $pluralModelLabel = 'Proveedores';
    protected static ?string $navigationLabel = 'Proveedores';
    // --- Fin Etiquetas ---

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('business_name')
                    ->label(__('Business Name'))
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                // Ocultar y fijar restaurant_id = 1
                Forms\Components\Select::make('restaurant_id')
                    ->hidden()
                    ->default(1),
                Forms\Components\TextInput::make('cuit')
                    ->label(__('CUIT'))
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('phone')
                    ->label(__('Phone'))
                    ->tel()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->label(__('Email'))
                    ->email()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('business_name')
                    ->label(__('Business Name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('cuit')
                    ->label(__('CUIT'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label(__('Phone'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label(__('Email'))
                    ->searchable(),
                // Ocultar columna restaurant
                Tables\Columns\TextColumn::make('restaurant.name')
                    ->label(__('Restaurant'))
                    ->sortable()
                    ->searchable()
                    ->hidden(),
            ])
            ->filters([
                // (No hemos añadido SoftDeletes a Proveedores, así que no es necesario)
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
    
    public static function getRelations(): array
    {
        return [
            RelationManagers\IngredientsRelationManager::class,
        ];
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProviders::route('/'),
            'create' => Pages\CreateProvider::route('/create'),
            'edit' => Pages\EditProvider::route('/{record}/edit'),
        ];
    }
    
    // Filtrar solo registros del restaurante ID = 1
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('restaurant_id', 1);
    }
}