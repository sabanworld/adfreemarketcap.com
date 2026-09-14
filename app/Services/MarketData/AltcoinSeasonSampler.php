<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Models\Coin;
use Illuminate\Database\Eloquent\Collection;

/**
 * Who counts as an "altcoin" for the altcoin season index.
 *
 * Two pipelines need the same answer: the job that fetches 90-day history, and the snapshot that
 * scores it. If they disagree the index is computed over coins whose history was never fetched,
 * so the membership rule lives here once.
 */
class AltcoinSeasonSampler
{
    /**
     * Stablecoins and wrapped or staked derivatives are excluded because they track something
     * else by design: a dollar peg, or the asset they wrap. Neither is a bet on an altcoin.
     *
     * @return Collection<int, Coin>
     */
    public function alts(): Collection
    {
        return Coin::query()
            ->whereNotNull('rank')
            ->where('slug', '!=', 'bitcoin')
            ->whereNotIn('symbol', $this->excludedSymbols())
            ->orderBy('rank')
            ->limit($this->size())
            ->get();
    }

    public function bitcoin(): ?Coin
    {
        return Coin::query()->where('slug', 'bitcoin')->first();
    }

    /**
     * The alts plus Bitcoin, which is the benchmark every alt is measured against and therefore
     * needs the same 90-day history.
     *
     * @return Collection<int, Coin>
     */
    public function sample(): Collection
    {
        $alts = $this->alts();
        $bitcoin = $this->bitcoin();

        return $bitcoin === null ? $alts : $alts->prepend($bitcoin);
    }

    public function size(): int
    {
        return max(1, (int) config('marketdata.altcoin_season.top_n', 50));
    }

    /**
     * @return list<string>
     */
    public function excludedSymbols(): array
    {
        /** @var list<string> $symbols */
        $symbols = config('marketdata.altcoin_season.exclude_symbols', []);

        return array_map(strtoupper(...), $symbols);
    }
}
