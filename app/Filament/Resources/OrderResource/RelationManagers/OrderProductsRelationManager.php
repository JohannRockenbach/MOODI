<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Forms;
use App\Models\Product;

class OrderProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'orderProducts';

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Select::make('product_id')
                ->label('Producto')
                ->options(fn() => Product::all()->pluck('name','id'))
                ->required(),

            Forms\Components\TextInput::make('quantity')
                ->numeric()
                ->required()
                ->default(1),

            Forms\Components\Textarea::make('notes')
                ->rows(2)
                ->columnSpanFull(),
        ]);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Producto')
                    ->searchable()
                    ->weight('bold'),
                
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Cantidad')
                    ->alignCenter()
                    ->badge()
                    ->color('info'),
                
                Tables\Columns\TextColumn::make('price')
                    ->label('Precio Unit.')
                    ->money('ARS', locale: 'es_AR')
                    ->alignEnd(),
                
                Tables\Columns\TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->state(fn($record) => $record->quantity * $record->price)
                    ->money('ARS', locale: 'es_AR')
                    ->weight('bold')
                    ->color('success')
                    ->alignEnd(),
                
                Tables\Columns\TextColumn::make('notes')
                    ->label('Notas')
                    ->limit(50)
                    ->placeholder('Sin notas')
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Añadido')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->contentFooter(function ($livewire) {
                $total = $livewire->getOwnerRecord()->orderProducts->sum(function ($item) {
                    return $item->quantity * $item->price;
                });
                
                return view('filament.tables.footer.order-total', [
                    'total' => $total
                ]);
            });
    }
}
