<?php

declare(strict_types=1);

namespace App\Filament\Resources\SyncRuns\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SyncRunInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('type'),
                TextEntry::make('provider'),
                TextEntry::make('status')->badge(),
                TextEntry::make('records_processed'),
                TextEntry::make('message'),
                TextEntry::make('error'),
                TextEntry::make('started_at')->dateTime(),
                TextEntry::make('finished_at')->dateTime(),
            ]);
    }
}
