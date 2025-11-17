<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestOrders extends BaseWidget
{
    protected static ?string $heading = 'Últimos 5 Pedidos';

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Order::query()
                    ->with(['user', 'table', 'orderProducts.product'])
                    ->latest('created_at')
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Cliente/Mozo')
                    ->searchable()
                    ->sortable()
                    ->default('N/A'),

                Tables\Columns\TextColumn::make('table.number')
                    ->label('Mesa')
                    ->sortable()
                    ->badge()
                    ->color('primary')
                    ->default('N/A'),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->getStateUsing(function (Order $record): float {
                        // Calcular el total desde orderProducts
                        return $record->orderProducts->sum(function ($orderProduct) {
                            return $orderProduct->quantity * $orderProduct->product->price;
                        });
                    })
                    ->formatStateUsing(function ($state) {
                        return '$ ' . number_format((float) $state, 2, ',', '.');
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending', 'en_proceso' => 'warning',
                        'in_progress' => 'info',
                        'served', 'servido' => 'success',
                        'cancelled', 'pagado' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'en_proceso' => 'En Proceso',
                        'servido' => 'Servido',
                        'pagado' => 'Pagado',
                        'pending' => 'Pendiente',
                        'in_progress' => 'En Progreso',
                        'served' => 'Servido',
                        'cancelled' => 'Cancelado',
                        default => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->paginated(false);
    }
}
