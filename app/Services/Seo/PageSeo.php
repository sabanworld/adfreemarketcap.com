<?php

declare(strict_types=1);

namespace App\Services\Seo;

final readonly class PageSeo
{
    /**
     * @param  list<array<string, mixed>>  $jsonLd
     */
    public function __construct(
        public string $title,
        public string $description,
        public string $canonical,
        public ?string $image = null,
        public string $ogType = 'website',
        public array $jsonLd = [],
        public string $robots = 'index,follow',
    ) {}
}
