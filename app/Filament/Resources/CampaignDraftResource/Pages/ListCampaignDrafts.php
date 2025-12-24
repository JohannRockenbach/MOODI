<?php

namespace App\Filament\Resources\CampaignDraftResource\Pages;

use App\Filament\Resources\CampaignDraftResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCampaignDrafts extends ListRecords
{
    protected static string $resource = CampaignDraftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
