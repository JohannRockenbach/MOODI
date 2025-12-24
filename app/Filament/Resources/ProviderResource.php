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
                    ->label('Razón Social')
                    ->placeholder('Ingrese el nombre o razón social del proveedor')
                    ->helperText('Nombre comercial o legal de la empresa proveedora')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                // Ocultar y fijar restaurant_id = 1
                Forms\Components\Select::make('restaurant_id')
                    ->hidden()
                    ->default(1),
                Forms\Components\TextInput::make('cuit')
                    ->label('CUIT')
                    ->placeholder('XX-XXXXXXXX-X')
                    ->helperText('Clave Única de Identificación Tributaria')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('phone')
                    ->label('Teléfono')
                    ->placeholder('+54 9 11 XXXX-XXXX')
                    ->tel()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->label('Correo Electrónico')
                    ->placeholder('contacto@proveedor.com')
                    ->email()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('business_name')
                    ->label('Razón Social')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('cuit')
                    ->label('CUIT')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable()
                    ->icon('heroicon-o-phone'),
                Tables\Columns\TextColumn::make('email')
                    ->label('Correo Electrónico')
                    ->searchable()
                    ->icon('heroicon-o-envelope')
                    ->copyable()
                    ->copyMessage('Correo copiado'),
                // Ocultar columna restaurant
                Tables\Columns\TextColumn::make('restaurant.name')
                    ->label('Restaurante')
                    ->sortable()
                    ->searchable()
                    ->hidden(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make()
                    ->label('Proveedores eliminados'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar'),
                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar'),
                Tables\Actions\RestoreAction::make()
                    ->label('Restaurar'),
                Tables\Actions\ForceDeleteAction::make()
                    ->label('Eliminar permanentemente'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Eliminar seleccionados'),
                    Tables\Actions\RestoreBulkAction::make()
                        ->label('Restaurar seleccionados'),
                    Tables\Actions\ForceDeleteBulkAction::make()
                        ->label('Eliminar permanentemente'),
                ]),
            ])
            ->emptyStateHeading('No hay proveedores registrados')
            ->emptyStateDescription('Comienza agregando tu primer proveedor usando el botón superior.')
            ->emptyStateIcon('heroicon-o-truck');
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