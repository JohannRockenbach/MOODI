<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\CajaResource;
use App\Filament\Resources\SaleResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListSales extends ListRecords
{
    protected static string $resource = SaleResource::class;

    public ?int $cajaIdContext = null;

    public function mount(): void
    {
        parent::mount();

        $filterValue = data_get($this->tableFilters, 'caja_id.value');

        if (is_numeric($filterValue)) {
            $this->cajaIdContext = (int) $filterValue;
        }
    }

    protected function getHeaderActions(): array
    {
        $actions = [
            Actions\CreateAction::make(),
        ];

        if ($this->cajaIdContext) {
            $actions[] = Actions\Action::make('volver_a_cajas')
                ->label('Volver a Cajas')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(CajaResource::getUrl('index'));
        }

        return $actions;
    }

    public function getTabs(): array
    {
        $baseQuery = SaleResource::getEloquentQuery();

        return [
            'activas' => Tab::make('No anuladas')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('annulled_at'))
                ->badge((clone $baseQuery)->whereNull('annulled_at')->count()),

            'anuladas' => Tab::make('Anuladas')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('annulled_at'))
                ->badge((clone $baseQuery)->whereNotNull('annulled_at')->count()),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'activas';
    }
}
