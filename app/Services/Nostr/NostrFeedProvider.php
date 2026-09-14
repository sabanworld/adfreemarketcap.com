<?php

declare(strict_types=1);

namespace App\Services\Nostr;

use App\Services\Nostr\DTOs\NostrNoteData;
use App\Support\Bech32;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class NostrFeedProvider
{
    /**
     * @param  list<string>  $hashtags  Lowercase tags without `#`; used as `#t` filter on Divine.
     * @return list<NostrNoteData>
     */
    public function fetchNotesForPubkey(string $pubkeyHex, int $limit = 12, array $hashtags = []): array
    {
        $errors = [];

        foreach ($this->backends() as $backend) {
            try {
                $notes = match ($backend['driver']) {
                    'divine' => $this->fetchViaDivine($backend['base_url'], $pubkeyHex, $limit, $hashtags),
                    'nostr_band' => $this->fetchViaNostrBand($backend['base_url'], $pubkeyHex, $limit),
                    default => throw new RuntimeException('Unknown Nostr backend driver: ' . $backend['driver']),
                };

                return $notes;
            } catch (Throwable $exception) {
                $errors[] = $backend['driver'] . ': ' . $exception->getMessage();
            }
        }

        throw new RuntimeException('Nostr notes fetch failed on every backend. ' . implode(' | ', $errors));
    }

    /**
     * @param  list<string>  $hashtags
     * @return list<NostrNoteData>
     */
    public function fetchNotesForNpub(string $npub, int $limit = 12, array $hashtags = []): array
    {
        return $this->fetchNotesForPubkey(Bech32::npubToHex($npub), $limit, $hashtags);
    }

    /**
     * @return list<array{driver: string, base_url: string}>
     */
    private function backends(): array
    {
        /** @var list<array{driver?: string, base_url?: string}> $configured */
        $configured = config('nostr.backends', []);
        $backends = [];

        foreach ($configured as $row) {
            $driver = isset($row['driver']) && is_string($row['driver']) ? $row['driver'] : null;
            $baseUrl = isset($row['base_url']) && is_string($row['base_url']) ? rtrim($row['base_url'], '/') : null;
            if ($driver === null || $baseUrl === null || $baseUrl === '') {
                continue;
            }
            $backends[] = ['driver' => $driver, 'base_url' => $baseUrl];
        }

        if ($backends === []) {
            $backends[] = [
                'driver' => 'divine',
                'base_url' => rtrim((string) config('nostr.divine_base_url', 'https://gateway.divine.video'), '/'),
            ];
        }

        return $backends;
    }

    /**
     * @param  list<string>  $hashtags
     * @return list<NostrNoteData>
     */
    private function fetchViaDivine(string $baseUrl, string $pubkeyHex, int $limit, array $hashtags): array
    {
        $filter = [
            'authors' => [$pubkeyHex],
            'kinds' => [1],
            'limit' => $limit,
        ];

        if ($hashtags !== []) {
            $filter['#t'] = array_values($hashtags);
        }

        $encoded = rtrim(strtr(base64_encode((string) json_encode($filter)), '+/', '-_'), '=');

        $response = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->timeout((int) config('nostr.http_timeout', 12))
            ->connectTimeout((int) config('nostr.http_connect_timeout', 5))
            ->get('/query', ['filter' => $encoded]);

        throw_unless($response->successful(), new RuntimeException(
            'Divine Nostr gateway failed: ' . $response->status() . ' ' . $response->body()
        ));

        return $this->parseNotes($response->json());
    }

    /**
     * @return list<NostrNoteData>
     */
    private function fetchViaNostrBand(string $baseUrl, string $pubkeyHex, int $limit): array
    {
        try {
            $response = Http::baseUrl($baseUrl)
                ->acceptJson()
                ->timeout((int) config('nostr.http_timeout', 12))
                ->connectTimeout((int) config('nostr.http_connect_timeout', 5))
                ->get('/v0/notes/' . $pubkeyHex, ['limit' => $limit]);
        } catch (ConnectionException|RequestException $exception) {
            throw new RuntimeException('Nostr.Band request failed: ' . $exception->getMessage(), 0, $exception);
        }

        throw_unless($response->successful(), new RuntimeException(
            'Nostr.Band notes fetch failed: ' . $response->status() . ' ' . $response->body()
        ));

        return $this->parseNotes($response->json());
    }

    /**
     * @return list<NostrNoteData>
     */
    private function parseNotes(mixed $payload): array
    {
        $events = [];

        if (is_array($payload)) {
            if (array_is_list($payload)) {
                $events = $payload;
            } elseif (isset($payload['notes']) && is_array($payload['notes'])) {
                $events = $payload['notes'];
            } elseif (isset($payload['events']) && is_array($payload['events'])) {
                $events = $payload['events'];
            } elseif (isset($payload['data']) && is_array($payload['data'])) {
                $events = $payload['data'];
            }
        }

        $notes = [];

        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }

            if (isset($event['event']) && is_array($event['event'])) {
                $authorName = isset($event['author']['name']) && is_string($event['author']['name'])
                    ? $event['author']['name']
                    : (isset($event['author']['display_name']) && is_string($event['author']['display_name'])
                        ? $event['author']['display_name']
                        : null);
                $event = $event['event'];
            } else {
                $authorName = null;
            }

            $id = isset($event['id']) && is_string($event['id']) ? $event['id'] : null;
            $pubkey = isset($event['pubkey']) && is_string($event['pubkey']) ? $event['pubkey'] : null;
            $content = isset($event['content']) && is_string($event['content']) ? $event['content'] : null;
            $createdAt = isset($event['created_at']) && is_numeric($event['created_at']) ? (int) $event['created_at'] : null;
            $kind = isset($event['kind']) ? (int) $event['kind'] : 1;

            if ($id === null || $pubkey === null || $content === null || $createdAt === null || $kind !== 1) {
                continue;
            }

            $notes[] = new NostrNoteData(
                eventId: $id,
                pubkey: $pubkey,
                content: $content,
                createdAt: $createdAt,
                authorName: $authorName,
                hashtags: $this->extractHashtags($event),
            );
        }

        return $notes;
    }

    /**
     * @param  array<string, mixed>  $event
     * @return list<string>
     */
    private function extractHashtags(array $event): array
    {
        $tags = [];
        $rawTags = $event['tags'] ?? null;
        if (! is_array($rawTags)) {
            return [];
        }

        foreach ($rawTags as $tag) {
            if (! is_array($tag) || ! isset($tag[0], $tag[1])) {
                continue;
            }
            if ($tag[0] !== 't' || ! is_string($tag[1]) || $tag[1] === '') {
                continue;
            }
            $tags[] = strtolower($tag[1]);
        }

        return array_values(array_unique($tags));
    }
}
