<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Named Redis queues Horizon workers drain.
 *
 * Local runs one supervisor that listens in this priority order. Production
 * runs a supervisor per queue so a long chart pass cannot starve a visit sync
 * or a watchlist email.
 */
final class QueueName
{
    /** Scheduled rankings, Dex lists, hot tickers, currency rates. */
    public const SYNC = 'sync';

    /** On-page freshness for coins and Dex pairs the visitor is looking at. */
    public const VISIT = 'visit';

    /** Long or bursty provider work that can wait (charts, platforms, Nostr). */
    public const HEAVY = 'heavy';

    /** Watchlist move alerts and the daily recap. */
    public const MAIL = 'mail';

    /**
     * Priority order for a single local worker that drains every queue.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::MAIL,
            self::VISIT,
            self::SYNC,
            self::HEAVY,
        ];
    }
}
