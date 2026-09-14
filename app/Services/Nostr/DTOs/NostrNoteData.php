<?php

declare(strict_types=1);

namespace App\Services\Nostr\DTOs;

final readonly class NostrNoteData
{
    /**
     * @param  list<string>  $hashtags  Lowercase `t` tag values from the event.
     */
    public function __construct(
        public string $eventId,
        public string $pubkey,
        public string $content,
        public int $createdAt,
        public ?string $authorName = null,
        public array $hashtags = [],
    ) {}
}
