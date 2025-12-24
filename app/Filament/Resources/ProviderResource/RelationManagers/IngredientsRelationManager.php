<?php

namespace App\Filament\Resources\ProviderResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class IngredientsRelationManager extends RelationManager
{
    protected static string $relationship = 'ingredients';
    
    protected static ?string $title = 'Ingredientes';
    protected static ?string $modelLabel = 'ingrediente';
    protected static ?string $pluralModelLabel = 'ingredientes';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['restaurant_id'] = 1;
        return $data;
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Datos del Ingrediente')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Información General')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nombre del Ingrediente')
                                    ->required()
                                    ->maxLength(255)
                                    ->disabled(fn ($context) => $context === 'edit')
                                    ->dehydrated(fn ($context) => $context !== 'edit'),

                                Forms\Components\Select::make('measurement_unit')
                                    ->label('Unidad de Medida')
                                    ->required()
                                    ->options([
                                        'kg' => 'Kilogramos (kg)',
                                        'g' => 'Gramos (g)',
                                        'l' => 'Litros (l)',
                                        'ml' => 'Mililitros (ml)',
                                        'unidades' => 'Unidades',
                                        'paquetes' => 'Paquetes',
                                        'cajas' => 'Cajas',
                                        'latas' => 'Latas',
                                        'botellas' => 'Botellas',
                                    ])
                                    ->searchable()
                                    ->placeholder('Seleccione una unidad')
                                    ->disabled(fn ($context) => $context === 'edit')
                                    ->dehydrated(fn ($context) => $context !== 'edit'),

                                Forms\Components\TextInput::make('current_stock')
                                    ->label('Stock Actual')
                                    ->numeric()
                                    ->default(0)
                                    ->disabled(fn ($context) => $context === 'edit')
                                    ->dehydrated(fn ($context) => $context !== 'edit')
                                    ->helperText('El stock solo puede modificarse desde la pantalla de Ingredientes'),

                                Forms\Components\TextInput::make('min_stock')
                                    ->label('Stock Mínimo')
                                    ->numeric()
                                    ->default(0)
                                    ->disabled(fn ($context) => $context === 'edit')
                                    ->dehydrated(fn ($context) => $context !== 'edit')
                                    ->helperText('Solo editable desde la pantalla de Ingredientes'),

                                Forms\Components\Hidden::make('restaurant_id')
                                    ->default(1)
                                    ->dehydrated(fn ($context) => $context !== 'edit'),
                            ])->columns(2),

                        Forms\Components\Tabs\Tab::make('Información de Compra')
                            ->schema([
                                Forms\Components\TextInput::make('purchase_price')
                                    ->label('Precio de Compra')
                                    ->numeric()
                                    ->required()
                                    ->prefix('$')
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->helperText('Precio al que este proveedor vende este ingrediente'),

                                Forms\Components\TextInput::make('purchase_unit')
                                    ->label('Unidad de Compra')
                                    ->maxLength(255)
                                    ->placeholder('Ej: Bolsa de 25kg, Caja de 12 unidades')
                                    ->helperText('Opcional: Formato en que se compra'),
                            ])->columns(2),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('measurement_unit')
                    ->label('Unidad')
                    ->searchable()
                    ->badge()
                    ->color('info'),

                // COLUMNA PIVOT: Precio de compra
                Tables\Columns\TextColumn::make('pivot.purchase_price')
                    ->label('Precio de Compra')
                    ->money('ARS')
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),

                // COLUMNA PIVOT: Unidad de compra
                Tables\Columns\TextColumn::make('pivot.purchase_unit')
                    ->label('Unidad de Compra')
                    ->placeholder('No especificada')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('current_stock')
                    ->label('Stock Actual')
                    ->numeric()
                    ->sortable()
                    ->color(fn ($record) => $record->current_stock <= $record->min_stock ? 'danger' : 'success')
                    ->weight(fn ($record) => $record->current_stock <= $record->min_stock ? 'bold' : 'normal')
                    ->suffix(fn ($record) => ' ' . $record->measurement_unit),

                Tables\Columns\TextColumn::make('min_stock')
                    ->label('Stock Mínimo')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('warning')
                    ->suffix(fn ($record) => ' ' . $record->measurement_unit),

                Tables\Columns\TextColumn::make('restaurant.name')
                    ->label('Restaurante')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('stock_bajo')
                    ->label('Stock Bajo')
                    ->query(fn (Builder $query): Builder => $query->whereColumn('current_stock', '<=', 'min_stock'))
                    ->toggle(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Agregar Ingrediente'),
                Tables\Actions\AttachAction::make()
                    ->label('Asociar Ingrediente Existente')
                    ->preloadRecordSelect()
                    ->modalHeading('Asociar Ingrediente')
                    ->modalDescription('Seleccione el ingrediente que desea asociar a este proveedor. Puede agregar el precio de compra ahora o editarlo más tarde.')
                    ->form(fn (Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Forms\Components\TextInput::make('purchase_price')
                            ->label('Precio de Compra (opcional)')
                            ->numeric()
                            ->prefix('$')
                            ->minValue(0)
                            ->step(0.01)
                            ->helperText('Puede dejarlo vacío y completarlo después'),
                        Forms\Components\TextInput::make('purchase_unit')
                            ->label('Unidad de Compra (opcional)')
                            ->maxLength(255)
                            ->placeholder('Ej: Bolsa de 25kg, Caja de 12 unidades')
                            ->helperText('Puede dejarlo vacío y completarlo después'),
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar'),
                Tables\Actions\DetachAction::make()
                    ->label('Desasociar'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make()
                        ->label('Desasociar seleccionados'),
                ]),
            ])
            ->defaultSort('name', 'asc');
    }
}
