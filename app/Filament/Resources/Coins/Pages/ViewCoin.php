<?php

namespace App\Filament\Resources\Coins\Pages;

use App\Filament\Resources\Coins\CoinResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCoin extends ViewRecord
{
    protected static string $resource = CoinResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
