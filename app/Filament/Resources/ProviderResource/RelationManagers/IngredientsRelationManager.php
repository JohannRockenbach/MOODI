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

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre del Ingrediente')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('measurement_unit')
                    ->label('Unidad de Medida')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Ej: kg, litros, unidades')
                    ->helperText('Especifica la unidad de medida para este ingrediente'),

                Forms\Components\TextInput::make('current_stock')
                    ->label('Stock Actual')
                    ->numeric()
                    ->default(0)
                    ->required(),

                Forms\Components\TextInput::make('min_stock')
                    ->label('Stock Mínimo')
                    ->numeric()
                    ->default(0)
                    ->required()
                    ->helperText('Umbral mínimo de alerta'),

                Forms\Components\Select::make('restaurant_id')
                    ->label('Restaurante')
                    ->relationship('restaurant', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),

                // --- CAMPOS PIVOT (para la tabla ingredient_provider) ---
                Forms\Components\Section::make('Información de Compra')
                    ->description('Datos específicos de este proveedor')
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
                    ])
                    ->columns(2)
                    ->collapsible()
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
                    ->form(fn (Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect(),
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
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DetachAction::make()
                    ->label('Desasociar'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make()
                        ->label('Desasociar seleccionados'),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]))
            ->defaultSort('name', 'asc');
    }
}
