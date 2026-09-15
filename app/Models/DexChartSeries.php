<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

class DexChartSeries extends Model
{
    public const SERIES_INTRADAY = 'intraday';

    public const SERIES_SHORT = 'short';

    public const SERIES_DAILY = 'daily';

    /**
     * @var list<string>
     */
    public const SERIES_KEYS = [
        self::SERIES_INTRADAY,
        self::SERIES_SHORT,
        self::SERIES_DAILY,
    ];

    protected $fillable = [
        'chartable_type',
        'chartable_id',
        'series',
        'points',
        'synced_at',
    ];

    public function chartable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isStale(?int $minutes = null): bool
    {
        $minutes ??= match ($this->series) {
            self::SERIES_INTRADAY => (int) config('marketdata.sync.dex_chart_intraday_stale_minutes', 30),
            self::SERIES_SHORT => (int) config('marketdata.sync.dex_chart_short_stale_minutes', 120),
            default => (int) config('marketdata.sync.dex_chart_daily_stale_minutes', 720),
        };

        if (! $this->synced_at instanceof Carbon) {
            return true;
        }

        return $this->synced_at->lte(now()->subMinutes(max(1, $minutes)));
    }

    protected function casts(): array
    {
        return [
            'points' => 'array',
            'synced_at' => 'datetime',
        ];
    }
}
