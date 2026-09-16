<?php

declare(strict_types=1);

namespace App\Services\Nostr;

use App\Models\NostrNote;
use App\Models\SyncRun;
use App\Services\Nostr\DTOs\NostrNoteData;
use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;

class NostrFeedSyncService
{
    public function __construct(
        private readonly NostrFeedProvider $provider,
    ) {}

    public function sync(?string $coinSlug = null): SyncRun
    {
        $run = SyncRun::query()->create([
            'type' => 'nostr_feed',
            'status' => SyncRun::STATUS_RUNNING,
            'provider' => 'nostr-http',
            'started_at' => now(),
        ]);

        try {
            /** @var array<string, array{authors?: list<array{npub: string, label?: string}>, hashtags: list<string>}> $feeds */
            $feeds = config('nostr.feeds', []);
            if (filled($coinSlug)) {
                $feeds = array_intersect_key($feeds, [$coinSlug => true]);
            }

            $processed = 0;
            $authorFailures = 0;
            $authorAttempts = 0;
            $limit = max(1, (int) config('nostr.notes_per_author', 40));
            $maxAgeDays = max(1, (int) config('nostr.max_age_days', 90));
            $cutoff = now()->subDays($maxAgeDays)->getTimestamp();
            /** @var list<array{npub: string, label?: string}> $defaultAuthors */
            $defaultAuthors = config('nostr.authors', []);

            // Fetch each curated author once, then apply per-coin topic filters.
            $authorNotes = [];
            $authorLabels = [];
            $authorsSucceeded = [];

            foreach ($this->uniqueAuthors($feeds, $defaultAuthors) as $author) {
                $npub = $author['npub'];
                $authorAttempts++;
                $authorLabels[$npub] = $author['label'] ?? null;

                try {
                    $authorNotes[$npub] = $this->provider->fetchNotesForNpub($npub, $limit, []);
                    $authorsSucceeded[$npub] = true;
                } catch (Throwable $exception) {
                    $authorFailures++;
                    report($exception);
                    $authorNotes[$npub] = [];
                    $authorsSucceeded[$npub] = false;
                }
            }

            foreach ($feeds as $slug => $feed) {
                $topics = array_values(array_filter(array_map(
                    strtolower(...),
                    $feed['hashtags'] ?? [],
                )));

                if ($topics === []) {
                    continue;
                }

                $authors = $feed['authors'] ?? $defaultAuthors;
                $keptIds = [];
                $feedAuthorsSucceeded = 0;

                foreach ($authors as $author) {
                    $npub = $author['npub'] ?? null;
                    if (! is_string($npub) || $npub === '' || ! array_key_exists($npub, $authorNotes)) {
                        continue;
                    }

                    if ($authorsSucceeded[$npub] ?? false) {
                        $feedAuthorsSucceeded++;
                    }

                    $label = isset($author['label']) && is_string($author['label'])
                        ? $author['label']
                        : ($authorLabels[$npub] ?? null);

                    $matched = $this->filterRelevantNotes($authorNotes[$npub], $topics, $cutoff);

                    foreach ($matched as $note) {
                        NostrNote::query()->updateOrCreate(
                            ['event_id' => $note->eventId],
                            [
                                'coin_slug' => $slug,
                                'pubkey' => $note->pubkey,
                                'author_name' => $note->authorName ?? $label,
                                'author_npub' => $npub,
                                'content' => $note->content,
                                'published_at' => Carbon::createFromTimestamp($note->createdAt),
                                'synced_at' => now(),
                            ],
                        );
                        $keptIds[] = $note->eventId;
                        $processed++;
                    }
                }

                if ($feedAuthorsSucceeded > 0) {
                    if ($keptIds === []) {
                        NostrNote::query()->where('coin_slug', $slug)->delete();
                    } else {
                        NostrNote::query()
                            ->where('coin_slug', $slug)
                            ->whereNotIn('event_id', $keptIds)
                            ->delete();
                    }
                }
            }

            $this->pruneExpired();

            $message = 'Synced Nostr community notes.';
            if ($authorFailures > 0) {
                $message .= " {$authorFailures} author fetch(es) failed and were skipped.";
            }

            if ($authorAttempts > 0 && $authorFailures >= $authorAttempts) {
                $run->markFailed($message);
                throw new RuntimeException($message);
            }

            $run->markSucceeded($processed, $message);

            return $run->fresh();
        } catch (Throwable $throwable) {
            if ($run->fresh()?->status !== SyncRun::STATUS_FAILED) {
                $run->markFailed($throwable->getMessage());
            }

            throw $throwable;
        }
    }

    /**
     * @param  array<string, array{authors?: list<array{npub: string, label?: string}>, hashtags?: list<string>}>  $feeds
     * @param  list<array{npub: string, label?: string}>  $defaultAuthors
     * @return list<array{npub: string, label?: string|null}>
     */
    private function uniqueAuthors(array $feeds, array $defaultAuthors): array
    {
        $unique = [];

        foreach ($feeds as $feed) {
            $authors = $feed['authors'] ?? $defaultAuthors;
            foreach ($authors as $author) {
                $npub = $author['npub'] ?? null;
                if (! is_string($npub) || $npub === '' || isset($unique[$npub])) {
                    continue;
                }

                $unique[$npub] = [
                    'npub' => $npub,
                    'label' => isset($author['label']) && is_string($author['label']) ? $author['label'] : null,
                ];
            }
        }

        return array_values($unique);
    }

    /**
     * Keep notes that are on-topic for the coin:
     * - explicit `#tag` in content or a Nostr `t` tag, or
     * - a whole-word topic term in the leading characters (so passing
     *   mentions late in an off-topic rant do not qualify).
     *
     * @param  list<NostrNoteData>  $notes
     * @param  list<string>  $topics
     * @return list<NostrNoteData>
     */
    private function filterRelevantNotes(array $notes, array $topics, int $cutoffTimestamp): array
    {
        $leadChars = max(40, (int) config('nostr.topic_lead_chars', 160));

        return array_values(array_filter($notes, function (NostrNoteData $note) use ($topics, $cutoffTimestamp, $leadChars): bool {
            if ($note->createdAt < $cutoffTimestamp) {
                return false;
            }

            $lead = mb_substr($note->content, 0, $leadChars);

            foreach ($topics as $topic) {
                if ($topic === '') {
                    continue;
                }

                if (in_array($topic, $note->hashtags, true)) {
                    return true;
                }

                if (preg_match('/#' . preg_quote($topic, '/') . '\b/iu', $note->content) === 1) {
                    return true;
                }

                if (preg_match('/\b' . preg_quote($topic, '/') . '\b/iu', $lead) === 1) {
                    return true;
                }
            }

            return false;
        }));
    }

    private function pruneExpired(): void
    {
        $retention = max(1, (int) config('nostr.retention_days', 90));
        $maxAge = max(1, (int) config('nostr.max_age_days', 90));
        $days = max($retention, $maxAge);

        NostrNote::query()
            ->where('published_at', '<', now()->subDays($days))
            ->delete();
    }
}
