<?php

declare(strict_types=1);

namespace App\Filament\Resources\Coins;

use App\Filament\Resources\Coins\Pages\EditCoin;
use App\Filament\Resources\Coins\Pages\ListCoins;
use App\Filament\Resources\Coins\Pages\ViewCoin;
use App\Filament\Resources\Coins\Schemas\CoinForm;
use App\Filament\Resources\Coins\Schemas\CoinInfolist;
use App\Filament\Resources\Coins\Tables\CoinsTable;
use App\Models\Coin;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CoinResource extends Resource
{
    protected static ?string $model = Coin::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return CoinForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CoinInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CoinsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCoins::route('/'),
            'view' => ViewCoin::route('/{record}'),
            'edit' => EditCoin::route('/{record}/edit'),
        ];
    }
}
