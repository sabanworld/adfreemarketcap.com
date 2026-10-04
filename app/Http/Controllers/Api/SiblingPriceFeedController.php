<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiblingPriceFeedController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $requested = collect(explode(',', (string) $request->query('symbols', '')))
            ->map(fn (string $symbol): string => strtoupper(trim($symbol)))
            ->filter()
            ->unique()
            ->values();

        $allowed = collect(config('sibling_api.allowed_symbols', []));

        if ($requested->isEmpty()) {
            $symbols = $allowed;
        } else {
            $symbols = $allowed->isEmpty()
                ? $requested
                : $requested->intersect($allowed)->values();
        }

        abort_if($symbols->isEmpty(), 422, 'No allowed symbols requested.');

        $coins = Coin::query()
            ->whereIn('symbol', $symbols->all())
            ->whereNotNull('price')
            ->orderBy('rank')
            ->get([
                'symbol',
                'name',
                'slug',
                'price',
                'percent_change_24h',
                'market_cap',
                'volume_24h',
                'image_url',
                'market_synced_at',
                'last_provider',
            ]);

        return response()->json([
            'source' => config('app.name'),
            'generated_at' => now()->toIso8601String(),
            'coins' => $coins->map(fn (Coin $coin): array => [
                'symbol' => strtoupper((string) $coin->symbol),
                'name' => $coin->name,
                'slug' => $coin->slug,
                'price_usd' => $coin->price !== null ? (float) $coin->price : null,
                'percent_change_24h' => $coin->percent_change_24h !== null ? (float) $coin->percent_change_24h : null,
                'market_cap' => $coin->market_cap !== null ? (float) $coin->market_cap : null,
                'volume_24h' => $coin->volume_24h !== null ? (float) $coin->volume_24h : null,
                'image_url' => $coin->image_url,
                'market_synced_at' => $coin->market_synced_at?->toIso8601String(),
                'last_provider' => $coin->last_provider,
            ])->values(),
        ]);
    }
}
