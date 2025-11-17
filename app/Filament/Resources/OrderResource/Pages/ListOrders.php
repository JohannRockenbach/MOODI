<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Todos')
                ->badge(Order::where('restaurant_id', 1)->count()),
            
            'pending' => Tab::make('Pendientes')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending'))
                ->badge(Order::where('restaurant_id', 1)->where('status', 'pending')->count()),
            
            'processing' => Tab::make('En Preparación')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'processing'))
                ->badge(Order::where('restaurant_id', 1)->where('status', 'processing')->count()),
            
            'completed' => Tab::make('Completados')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'completed'))
                ->badge(Order::where('restaurant_id', 1)->where('status', 'completed')->count()),
            
            'cancelled' => Tab::make('Cancelados')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'cancelled'))
                ->badge(Order::where('restaurant_id', 1)->where('status', 'cancelled')->count()),
        ];
    }
}
