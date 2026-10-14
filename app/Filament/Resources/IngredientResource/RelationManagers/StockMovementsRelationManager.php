<?php

namespace App\Filament\Resources\IngredientResource\RelationManagers;

use App\Models\StockMovement;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Historial de movimientos de stock del ingrediente (TAREA 2.2 / CU-003.1).
 * Solo lectura: tipo, cantidad, motivo, usuario y referencia por fecha desc.
 */
class StockMovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'stockMovements';

    protected static ?string $title = 'Historial de movimientos';

    public static function getTypeLabel(string $type): string
    {
        return match ($type) {
            StockMovement::TYPE_ENTRADA => 'Entrada',
            StockMovement::TYPE_SALIDA => 'Salida',
            StockMovement::TYPE_MERMA => 'Merma',
            StockMovement::TYPE_AJUSTE => 'Ajuste',
            default => ucfirst($type),
        };
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        StockMovement::TYPE_ENTRADA => 'success',
                        StockMovement::TYPE_SALIDA => 'info',
                        StockMovement::TYPE_MERMA => 'danger',
                        StockMovement::TYPE_AJUSTE => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => self::getTypeLabel($state)),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Cantidad')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('stock_before')
                    ->label('Stock previo')
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('stock_after')
                    ->label('Stock posterior')
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Motivo')
                    ->limit(40)
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuario')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('reference')
                    ->label('Referencia')
                    ->placeholder('-')
                    ->state(function (StockMovement $record): string {
                        if (! $record->reference_type || ! $record->reference_id) {
                            return '-';
                        }

                        $short = class_basename($record->reference_type);

                        return $short.' #'.$record->reference_id;
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        StockMovement::TYPE_ENTRADA => 'Entrada',
                        StockMovement::TYPE_SALIDA => 'Salida',
                        StockMovement::TYPE_MERMA => 'Merma',
                        StockMovement::TYPE_AJUSTE => 'Ajuste',
                    ]),
            ])
            // Solo lectura: historial inmutable.
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }
}