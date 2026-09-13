<?php

declare(strict_types=1);

namespace App\Support;

final class PlainText
{
    /**
     * Turn provider HTML (or entity-encoded plain text) into display-safe prose.
     */
    public static function fromHtml(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $plain = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = str_replace("\u{00A0}", ' ', $plain);
        $plain = trim($plain);

        return $plain === '' ? null : $plain;
    }
}
