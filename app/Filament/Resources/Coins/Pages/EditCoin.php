<?php

declare(strict_types=1);

namespace App\Filament\Resources\Coins\Pages;

use App\Filament\Resources\Coins\CoinResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCoin extends EditRecord
{
    protected static string $resource = CoinResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }
}
