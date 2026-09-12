<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;

/**
 * Maps Material Symbols icon names to the codepoints kept in our self-hosted
 * font subset. Views should go through <x-afmc.icon /> rather than this class.
 */
final class Icons
{
    /** @var array<string, string>|null */
    private static ?array $codepoints = null;

    public static function character(string $name): string
    {
        $codepoints = self::codepoints();

        throw_unless(
            isset($codepoints[$name]),
            new InvalidArgumentException(
                "Unknown icon [{$name}]. Add it to resources/fonts/material-symbols.json and run yarn icons:build."
            )
        );

        return mb_chr((int) hexdec($codepoints[$name]), 'UTF-8');
    }

    /**
     * @return array<string, string>
     */
    public static function codepoints(): array
    {
        if (self::$codepoints === null) {
            /** @var array<string, string> $decoded */
            $decoded = json_decode(File::get(self::manifestPath()), true, flags: JSON_THROW_ON_ERROR);

            self::$codepoints = $decoded;
        }

        return self::$codepoints;
    }

    public static function manifestPath(): string
    {
        return resource_path('fonts/material-symbols.json');
    }

    public static function flush(): void
    {
        self::$codepoints = null;
    }
}
