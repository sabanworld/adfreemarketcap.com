<?php

declare(strict_types=1);

namespace App\Filament\Resources\Coins\Tables;

use App\Jobs\SyncCoinDetail;
use App\Jobs\SyncGlobalData;
use App\Jobs\SyncMarketData;
use App\Models\Coin;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CoinsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('rank')->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('symbol')->searchable()->sortable(),
                TextColumn::make('price')->numeric(decimalPlaces: 6)->sortable(),
                TextColumn::make('percent_change_24h')->label('24h %')->numeric(decimalPlaces: 2)->sortable(),
                TextColumn::make('market_cap')->numeric(decimalPlaces: 0)->sortable(),
                TextColumn::make('last_provider')->badge(),
                TextColumn::make('market_synced_at')->dateTime()->sortable(),
            ])
            ->defaultSort('rank')
            ->filters([])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('refreshDetail')
                    ->label(__('Refresh detail'))
                    ->action(function (Coin $record): void {
                        SyncCoinDetail::dispatch($record->id);

                        Notification::make()
                            ->title(__('Detail sync queued'))
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([]),
                Action::make('syncMarkets')
                    ->label(__('Sync markets'))
                    ->action(function (): void {
                        SyncMarketData::dispatch();
                        SyncGlobalData::dispatch();

                        Notification::make()
                            ->title(__('Market sync queued'))
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
