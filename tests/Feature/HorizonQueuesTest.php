<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendWatchlistMoveAlerts;
use App\Jobs\SendWatchlistRecap;
use App\Jobs\SyncCoinCharts;
use App\Jobs\SyncCoinDetail;
use App\Jobs\SyncCoinInsights;
use App\Jobs\SyncCoinPlatforms;
use App\Jobs\SyncCoinTickers;
use App\Jobs\SyncCurrencyRates;
use App\Jobs\SyncDexPairDetail;
use App\Jobs\SyncDexPairs;
use App\Jobs\SyncDexTokenDetail;
use App\Jobs\SyncGlobalData;
use App\Jobs\SyncHotCoinCharts;
use App\Jobs\SyncHotCoinTickers;
use App\Jobs\SyncMarketData;
use App\Jobs\SyncMarketStatus;
use App\Jobs\SyncNinetyDayChanges;
use App\Jobs\SyncNostrFeed;
use App\Jobs\SyncStaleCoinDetails;
use App\Jobs\SyncTopCoinTickers;
use App\Support\QueueName;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class HorizonQueuesTest extends TestCase
{
    public function test_every_queued_job_targets_a_named_horizon_queue(): void
    {
        foreach ($this->expectedJobQueues() as $jobClass => $queue) {
            $job = $this->makeJob($jobClass);

            $this->assertSame(
                $queue,
                $job->queue,
                "{$jobClass} should land on the {$queue} queue.",
            );
        }
    }

    public function test_every_job_class_under_app_jobs_is_accounted_for(): void
    {
        $expected = $this->expectedJobQueues();
        $files = File::files(app_path('Jobs'));

        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $class = 'App\\Jobs\\' . $file->getFilenameWithoutExtension();
            $this->assertArrayHasKey(
                $class,
                $expected,
                "{$class} is missing from the Horizon queue map.",
            );
        }

        $this->assertCount(count($files), $expected);
    }

    public function test_local_and_production_provision_different_supervisors(): void
    {
        $environments = config('horizon.environments');

        $this->assertSame(['supervisor-local'], array_keys($environments['local']));
        $this->assertSame(['supervisor-local'], array_keys($environments['testing']));
        $this->assertSame(
            ['supervisor-sync', 'supervisor-visit', 'supervisor-heavy', 'supervisor-mail'],
            array_keys($environments['production']),
        );

        $this->assertSame(QueueName::all(), config('horizon.defaults.supervisor-local.queue'));
        $this->assertSame([QueueName::SYNC], config('horizon.defaults.supervisor-sync.queue'));
        $this->assertSame([QueueName::VISIT], config('horizon.defaults.supervisor-visit.queue'));
        $this->assertSame([QueueName::HEAVY], config('horizon.defaults.supervisor-heavy.queue'));
        $this->assertSame([QueueName::MAIL], config('horizon.defaults.supervisor-mail.queue'));
    }

    public function test_no_job_timeout_outruns_redis_retry_after_or_its_supervisor(): void
    {
        $retryAfter = (int) config('queue.connections.redis.retry_after');
        $this->assertGreaterThanOrEqual(660, $retryAfter);

        $queueTimeouts = [];

        foreach ($this->expectedJobQueues() as $jobClass => $queue) {
            $job = $this->makeJob($jobClass);
            $timeout = (int) $job->timeout;

            $this->assertLessThan(
                $retryAfter,
                $timeout,
                "{$jobClass} timeout ({$timeout}s) must stay under Redis retry_after ({$retryAfter}s).",
            );

            $queueTimeouts[$queue] = max($queueTimeouts[$queue] ?? 0, $timeout);
        }

        $supervisors = [
            'supervisor-sync' => [QueueName::SYNC],
            'supervisor-visit' => [QueueName::VISIT],
            'supervisor-heavy' => [QueueName::HEAVY],
            'supervisor-mail' => [QueueName::MAIL],
            'supervisor-local' => QueueName::all(),
        ];

        foreach ($supervisors as $name => $queues) {
            $supervisorTimeout = (int) config("horizon.defaults.{$name}.timeout");
            $longest = max(array_map(
                static fn (string $queue): int => $queueTimeouts[$queue] ?? 0,
                $queues,
            ));

            $this->assertGreaterThan(
                $longest,
                $supervisorTimeout,
                "{$name} timeout ({$supervisorTimeout}s) must exceed the longest job on its queues ({$longest}s).",
            );
        }
    }

    /**
     * @return array<class-string<ShouldQueue>, string>
     */
    private function expectedJobQueues(): array
    {
        return [
            SyncMarketData::class => QueueName::SYNC,
            SyncGlobalData::class => QueueName::SYNC,
            SyncMarketStatus::class => QueueName::SYNC,
            SyncDexPairs::class => QueueName::SYNC,
            SyncCurrencyRates::class => QueueName::SYNC,
            SyncHotCoinTickers::class => QueueName::SYNC,
            SyncCoinInsights::class => QueueName::SYNC,
            SyncCoinDetail::class => QueueName::VISIT,
            SyncCoinTickers::class => QueueName::VISIT,
            SyncCoinCharts::class => QueueName::VISIT,
            SyncDexPairDetail::class => QueueName::VISIT,
            SyncDexTokenDetail::class => QueueName::VISIT,
            SyncHotCoinCharts::class => QueueName::HEAVY,
            SyncTopCoinTickers::class => QueueName::HEAVY,
            SyncNinetyDayChanges::class => QueueName::HEAVY,
            SyncCoinPlatforms::class => QueueName::HEAVY,
            SyncNostrFeed::class => QueueName::HEAVY,
            SyncStaleCoinDetails::class => QueueName::HEAVY,
            SendWatchlistMoveAlerts::class => QueueName::MAIL,
            SendWatchlistRecap::class => QueueName::MAIL,
        ];
    }

    /**
     * @param  class-string<ShouldQueue>  $jobClass
     */
    private function makeJob(string $jobClass): ShouldQueue
    {
        return match ($jobClass) {
            SyncCoinDetail::class,
            SyncCoinTickers::class,
            SyncCoinCharts::class => new $jobClass(1),
            SyncDexPairDetail::class,
            SyncDexTokenDetail::class => new $jobClass(1),
            default => new $jobClass,
        };
    }
}
