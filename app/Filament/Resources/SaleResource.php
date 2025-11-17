<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SaleResource\Pages;
use App\Models\Sale;
use App\Models\Restaurant;
use App\Models\Order;
use App\Models\Caja;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use function __;

class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;

    // Use a heroicon that exists in the project's icon set to avoid Blade UI Icons errors
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationGroup = 'Ventas y Finanzas';
    protected static ?int $navigationSort = 2;

    // Etiquetas en español
    protected static ?string $modelLabel = 'Venta';
    protected static ?string $pluralModelLabel = 'Ventas';
    protected static ?string $navigationLabel = 'Ventas';

    public static function form(Form $form): Form
    {
        return $form->schema([
            // Ocultar y fijar restaurant_id = 1 (Sistema de restaurante único)
            Forms\Components\Hidden::make('restaurant_id')
                ->default(1),

            Forms\Components\Section::make('Información de la Venta')
                ->schema([
                    // Pedido (solo pedidos del restaurante 1)
                    Forms\Components\Select::make('order_id')
                        ->label('Pedido Nº')
                        ->relationship('order', 'id')
                        ->options(fn() => Order::where('restaurant_id', 1)->pluck('id', 'id'))
                        ->searchable()
                        ->required()
                        ->helperText('Selecciona el pedido asociado a esta venta')
                        ->columnSpan(1),

                    // Caja (solo cajas del restaurante 1)
                    Forms\Components\Select::make('caja_id')
                        ->label('Caja Nº')
                        ->relationship('caja', 'id')
                        ->options(fn() => Caja::where('restaurant_id', 1)
                            ->where('status', 'abierta')
                            ->pluck('id', 'id'))
                        ->searchable()
                        ->required()
                        ->helperText('Caja donde se registra la venta (solo cajas abiertas)')
                        ->columnSpan(1),

                    // Cajero
                    Forms\Components\Select::make('cashier_id')
                        ->label('Cajero')
                        ->relationship('cashier', 'name')
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->helperText('Usuario que procesa la venta')
                        ->columnSpan(1),

                    // Método de Pago
                    Forms\Components\Select::make('payment_method')
                        ->label('Método de Pago')
                        ->options([
                            'cash' => 'Efectivo',
                            'card' => 'Tarjeta',
                            'transfer' => 'Transferencia',
                        ])
                        ->required()
                        ->native(false)
                        ->helperText('Forma de pago de la venta')
                        ->columnSpan(1),

                    // Estado (solo opciones para creación manual, sin 'failed')
                    Forms\Components\Select::make('status')
                        ->label('Estado')
                        ->options([
                            'paid' => 'Pagado',
                            'pending' => 'Pendiente',
                        ])
                        ->default('paid')
                        ->required()
                        ->native(false)
                        ->helperText('Estado de la venta (Fallido no es una opción manual)')
                        ->columnSpan(1),

                    // Monto Total (readOnly - se puede calcular desde el pedido)
                    Forms\Components\TextInput::make('total_amount')
                        ->label('Monto Total')
                        ->numeric()
                        ->required()
                        ->readOnly()
                        ->prefix('$')
                        ->helperText('Total de la venta (calculado desde el pedido)')
                        ->columnSpan(1),
                ])
                ->columns(2)
                ->collapsible(),

            // Descuentos (relación muchos a muchos)
            Forms\Components\Section::make('Descuentos Aplicados')
                ->schema([
                    Forms\Components\Select::make('discounts')
                        ->label('Descuentos')
                        ->relationship('discounts', 'code')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->helperText('Descuentos aplicados a esta venta')
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // ID de la Venta
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                // Pedido Nº (con optimización N+1)
                Tables\Columns\TextColumn::make('order.id')
                    ->label('Pedido Nº')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('info')
                    ->prefix('Pedido #'),

                // Caja Nº (con optimización N+1)
                Tables\Columns\TextColumn::make('caja.id')
                    ->label('Caja Nº')
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->prefix('Caja #'),

                // Monto Total
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->sortable()
                    ->money('ars')
                    ->weight('bold')
                    ->icon('heroicon-o-currency-dollar'),

                // Método de Pago con Badge
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Método Pago')
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'cash' => 'success',
                        'card' => 'primary',
                        'transfer' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'cash' => 'Efectivo',
                        'card' => 'Tarjeta',
                        'transfer' => 'Transferencia',
                        default => ucfirst($state),
                    }),

                // Estado con Badge y Colores
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'paid' => 'Pagado',
                        'pending' => 'Pendiente',
                        'failed' => 'Fallido',
                        default => ucfirst($state),
                    }),

                // Cantidad de Descuentos (con optimización N+1)
                Tables\Columns\TextColumn::make('discounts_count')
                    ->label('Descuentos')
                    ->counts('discounts')
                    ->badge()
                    ->color('warning')
                    ->suffix(' desc.')
                    ->default(0),

                // Cajero
                Tables\Columns\TextColumn::make('cashier.name')
                    ->label('Cajero')
                    ->sortable()
                    ->searchable()
                    ->default('N/A')
                    ->icon('heroicon-o-user'),

                // Fecha de Creación
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->description(fn ($record) => $record->created_at->diffForHumans()),
            ])
            ->filters([
                // Filtro por Estado
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'paid' => 'Pagado',
                        'pending' => 'Pendiente',
                        'failed' => 'Fallido',
                    ]),

                // Filtro por Método de Pago
                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Método de Pago')
                    ->options([
                        'cash' => 'Efectivo',
                        'card' => 'Tarjeta',
                        'transfer' => 'Transferencia',
                    ]),

                // Filtro por Caja
                Tables\Filters\SelectFilter::make('caja_id')
                    ->label('Caja')
                    ->relationship('caja', 'id'),

                // Filtro por Fecha
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Desde'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Hasta'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn ($query, $date) => $query->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->color('primary'),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSales::route('/'),
            'create' => Pages\CreateSale::route('/create'),
            'edit' => Pages\EditSale::route('/{record}/edit'),
        ];
    }
    
    // Filtrar solo registros del restaurante ID = 1 + Optimización N+1
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->where('restaurant_id', 1) // Sistema de restaurante único
            ->with(['order', 'caja', 'cashier']) // Optimización N+1 para relaciones
            ->withCount('discounts'); // Optimización N+1 para conteo de descuentos
    }
}
