<?php

declare(strict_types=1);

namespace App\Filament\Resources\Coins\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CoinInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('rank'),
                TextEntry::make('name'),
                TextEntry::make('symbol'),
                TextEntry::make('price'),
                TextEntry::make('market_cap'),
                TextEntry::make('volume_24h'),
                TextEntry::make('last_provider'),
                TextEntry::make('market_synced_at')->dateTime(),
                TextEntry::make('detail_synced_at')->dateTime(),
                TextEntry::make('description')->columnSpanFull(),
            ]);
    }
}
