<?php

declare(strict_types=1);

namespace App\Filament\Resources\Coins\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CoinForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->disabled(),
                TextInput::make('symbol')->disabled(),
                TextInput::make('slug')->disabled(),
                TextInput::make('rank')->numeric()->disabled(),
                TextInput::make('price')->numeric()->disabled(),
                TextInput::make('last_provider')->disabled(),
                Textarea::make('description')->rows(8)->columnSpanFull(),
            ]);
    }
}
