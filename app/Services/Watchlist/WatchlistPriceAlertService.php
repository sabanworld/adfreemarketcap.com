<?php

declare(strict_types=1);

namespace App\Services\Watchlist;

use App\Mail\WatchlistMoveMail;
use App\Mail\WatchlistRecapMail;
use App\Models\Coin;
use App\Models\User;
use App\Models\WatchlistPriceAlert;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

final class WatchlistPriceAlertService
{
    /**
     * Highest mark at or below the absolute percentage. 0 means below the first mark.
     */
    public function bandFor(float $percent): int
    {
        $step = max(1, (int) config('watchlist_alerts.step_percent'));
        $first = max(1, (int) config('watchlist_alerts.first_percent'));
        $absolute = abs($percent);

        if ($absolute + 1e-6 < $first) {
            return 0;
        }

        $band = (int) floor(($absolute + 1e-6) / $step) * $step;

        return max($first, $band);
    }

    public function forget(User $user, Coin $coin): void
    {
        WatchlistPriceAlert::query()
            ->where('user_id', $user->id)
            ->where('coin_id', $coin->id)
            ->delete();
    }

    public function forgetUser(User $user): void
    {
        WatchlistPriceAlert::query()
            ->where('user_id', $user->id)
            ->delete();
    }

    public function sendMoveAlerts(): int
    {
        if (! config('watchlist_alerts.enabled')) {
            return 0;
        }

        $sent = 0;

        User::query()
            ->where('price_alerts_enabled', true)
            ->whereHas('watchlistItems')
            ->with(['watchedCoins'])
            ->lazyById()
            ->each(function (User $user) use (&$sent): void {
                $alerts = $this->pendingAlerts($user);

                if ($alerts === []) {
                    return;
                }

                Mail::to($user)->send(new WatchlistMoveMail($user, $alerts));
                $this->markSent($user, $alerts);
                $sent++;
            });

        return $sent;
    }

    public function sendDailyRecaps(): int
    {
        return $this->sendRecaps('daily');
    }

    public function sendWeeklyRecaps(): int
    {
        return $this->sendRecaps('weekly');
    }

    private function sendRecaps(string $period): int
    {
        if (! config('watchlist_alerts.enabled')) {
            return 0;
        }

        $column = $period === 'weekly' ? 'watchlist_weekly_recap_sent_on' : 'watchlist_recap_sent_on';
        $stamp = $period === 'weekly'
            ? now()->copy()->startOfWeek(Carbon::MONDAY)->toDateString()
            : now()->toDateString();
        $sent = 0;

        User::query()
            ->where('price_alerts_enabled', true)
            ->whereHas('watchlistItems')
            ->where(function ($query) use ($column, $stamp): void {
                $query->whereNull($column)
                    ->orWhere($column, '<', $stamp);
            })
            ->with(['watchedCoins'])
            ->lazyById()
            ->each(function (User $user) use ($column, $stamp, $period, &$sent): void {
                if ($user->watchedCoins->isEmpty()) {
                    return;
                }

                Mail::to($user)->send(new WatchlistRecapMail($user, $user->watchedCoins, $period));
                $user->{$column} = $stamp;
                $user->save();
                $sent++;
            });

        return $sent;
    }

    /**
     * @return list<WatchlistAlertCoin>
     */
    private function pendingAlerts(User $user): array
    {
        /** @var Collection<string, WatchlistPriceAlert> $states */
        $states = WatchlistPriceAlert::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy(fn (WatchlistPriceAlert $row): string => $row->coin_id . ':' . $row->window);

        $alerts = [];

        foreach ($user->watchedCoins as $coin) {
            $lines = [];

            foreach ($this->windows() as $window => $column) {
                $line = $this->evaluateWindow($user, $coin, $window, $column, $states);

                if ($line instanceof WatchlistAlertLine) {
                    $lines[] = $line;
                }
            }

            if ($lines !== []) {
                $alerts[] = new WatchlistAlertCoin($coin, $lines);
            }
        }

        return $alerts;
    }

    /**
     * @param  Collection<string, WatchlistPriceAlert>  $states
     */
    private function evaluateWindow(User $user, Coin $coin, string $window, string $column, Collection $states): ?WatchlistAlertLine
    {
        $attributes = $coin->getAttributes();
        $raw = $attributes[$column] ?? null;

        if (! is_int($raw) && ! is_float($raw) && ! (is_string($raw) && is_numeric($raw))) {
            return null;
        }

        $percent = (float) $raw;
        $band = $this->bandFor($percent);
        $direction = $percent < 0 ? 'down' : 'up';
        $state = $states->get($coin->id . ':' . $window);

        if (! $state instanceof WatchlistPriceAlert) {
            $this->storeBaseline($user, $coin, $window, $band === 0 ? null : $direction, $band);

            return null;
        }

        if ($band === 0) {
            if ((int) $state->notified_percent !== 0 || $state->direction !== null) {
                $state->fill([
                    'notified_percent' => 0,
                    'direction' => null,
                ])->save();
            }

            return null;
        }

        $sameDirection = $state->direction === $direction;
        $notified = $sameDirection ? (int) $state->notified_percent : 0;

        if (! $sameDirection) {
            $state->fill([
                'direction' => $direction,
                'notified_percent' => 0,
            ])->save();
        }

        if ($band <= $notified) {
            if ($sameDirection && $band < $notified) {
                $state->fill([
                    'notified_percent' => $band,
                    'direction' => $direction,
                ])->save();
            }

            return null;
        }

        $emailedAt = $state->emailed_at;

        if ($emailedAt instanceof Carbon && $emailedAt->gt(now()->subMinutes($this->cooldownMinutes()))) {
            return null;
        }

        return new WatchlistAlertLine($window, $this->windowLabel($window), $percent, $band);
    }

    private function storeBaseline(User $user, Coin $coin, string $window, ?string $direction, int $band): void
    {
        WatchlistPriceAlert::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'coin_id' => $coin->id,
                'window' => $window,
            ],
            [
                'direction' => $direction,
                'notified_percent' => $band,
                'emailed_at' => null,
            ],
        );
    }

    /**
     * @param  list<WatchlistAlertCoin>  $alerts
     */
    private function markSent(User $user, array $alerts): void
    {
        $sentAt = now();

        foreach ($alerts as $alert) {
            foreach ($alert->lines as $line) {
                WatchlistPriceAlert::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'coin_id' => $alert->coin->id,
                        'window' => $line->window,
                    ],
                    [
                        'direction' => $line->percent < 0 ? 'down' : 'up',
                        'notified_percent' => $line->band,
                        'emailed_at' => $sentAt,
                    ],
                );
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function windows(): array
    {
        $windows = config('watchlist_alerts.windows');

        if (! is_array($windows)) {
            return [];
        }

        $resolved = [];

        foreach ($windows as $window => $column) {
            if (! is_string($window) || ! is_string($column) || $window === '' || $column === '') {
                continue;
            }

            $resolved[$window] = $column;
        }

        return $resolved;
    }

    private function windowLabel(string $window): string
    {
        return match ($window) {
            '1h' => __('the last hour'),
            '24h' => __('24 hours'),
            '7d' => __('7 days'),
            default => $window,
        };
    }

    private function cooldownMinutes(): int
    {
        return max(1, (int) config('watchlist_alerts.cooldown_minutes'));
    }
}
