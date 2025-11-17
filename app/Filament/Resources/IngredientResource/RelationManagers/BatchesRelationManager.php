<?php

namespace App\Filament\Resources\IngredientResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BatchesRelationManager extends RelationManager
{
    protected static string $relationship = 'batches';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('quantity')
                    ->label('Cantidad')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01)
                    ->helperText('Cantidad de ingrediente en este lote'),
                
                Forms\Components\DatePicker::make('purchase_date')
                    ->label('Fecha de Compra')
                    ->default(now())
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->required(),
                
                Forms\Components\DatePicker::make('expiration_date')
                    ->label('Fecha de Vencimiento')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->nullable()
                    ->helperText('Dejar vacío si el ingrediente no tiene vencimiento'),
                
                Forms\Components\Textarea::make('notes')
                    ->label('Notas')
                    ->maxLength(65535)
                    ->columnSpanFull()
                    ->placeholder('Observaciones sobre este lote (proveedor, calidad, etc.)'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('quantity')
            ->columns([
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Cantidad')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('purchase_date')
                    ->label('Fecha de Compra')
                    ->date('d/m/Y')
                    ->sortable()
                    ->icon('heroicon-o-calendar'),
                
                Tables\Columns\TextColumn::make('expiration_date')
                    ->label('Fecha de Vencimiento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('Sin vencimiento')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color(fn ($record) => $record->expiration_date && $record->expiration_date->isPast() ? 'danger' : 
                           ($record->expiration_date && $record->expiration_date->diffInDays(now()) <= 7 ? 'warning' : 'success')),
                
                Tables\Columns\TextColumn::make('notes')
                    ->label('Notas')
                    ->limit(30)
                    ->toggleable()
                    ->placeholder('-'),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Agregar Lote'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('purchase_date', 'desc');
    }
}
