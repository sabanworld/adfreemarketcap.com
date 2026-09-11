<?php

declare(strict_types=1);

namespace App\Filament\Resources\Coins\Pages;

use App\Filament\Resources\Coins\CoinResource;
use Filament\Resources\Pages\ListRecords;

class ListCoins extends ListRecords
{
    protected static string $resource = CoinResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
