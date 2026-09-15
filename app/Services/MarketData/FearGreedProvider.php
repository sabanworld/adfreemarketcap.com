<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Services\MarketData\DTOs\FearGreedData;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FearGreedProvider
{
    public function fetchLatest(): FearGreedData
    {
        $response = app(ProviderCallCounter::class)->count(
            Http::baseUrl((string) config('marketdata.alternative_me.base_url'))
                ->acceptJson()
                ->timeout(20),
            ProviderCallCounter::ALTERNATIVE_ME,
        )->get('/fng/', ['limit' => 1]);

        throw_unless($response->successful(), new RuntimeException(
            'Alternative.me fear and greed failed: ' . $response->status() . ' ' . $response->body()
        ));

        $row = $response->json('data.0');
        throw_unless(is_array($row), new RuntimeException('Alternative.me fear and greed payload was empty.'));

        $value = isset($row['value']) && is_numeric($row['value']) ? (int) $row['value'] : null;
        $classification = isset($row['value_classification']) && is_string($row['value_classification'])
            ? $row['value_classification']
            : null;

        throw_unless($value !== null && $classification !== null, new RuntimeException(
            'Alternative.me fear and greed payload was incomplete.'
        ));

        return new FearGreedData(
            value: max(0, min(100, $value)),
            classification: $classification,
        );
    }
}
