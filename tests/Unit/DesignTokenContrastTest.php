<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * resources/css/design-system/colors.css calls its contrast figures "verified, not assumed".
 * This is the verification: every pair the UI actually paints is computed from the tokens, in
 * both themes, against the WCAG 2.x formula. A ratio in a comment is a comment.
 *
 * The floors are the level the accessibility statement claims, WCAG 2.2 AA: 4.5:1 for text
 * (1.4.3) and 3:1 for a control's own graphics (1.4.11).
 */
class DesignTokenContrastTest extends TestCase
{
    private const TEXT = 4.5;

    private const GRAPHIC = 3.0;

    /**
     * Text roles and the grounds they are allowed to sit on. Nothing paints a label on
     * --surface-inverse, which is why that pair is absent: the inverted surfaces this UI has
     * are grounded on --ink-900 directly, and their text comes from the two inverse roles
     * checked further down.
     *
     * @var list<string>
     */
    private const TEXT_ROLES = [
        '--text-body',
        '--text-strong',
        '--text-muted',
        '--text-faint',
        '--text-brand',
        '--text-up',
        '--text-down',
        '--text-warn',
        '--text-link',
    ];

    /** @var list<string> */
    private const GROUNDS = [
        '--surface-page',
        '--surface-card',
        '--surface-sunken',
    ];

    public function test_every_text_role_is_readable_on_every_ground_in_both_themes(): void
    {
        foreach (['light', 'dark'] as $theme) {
            foreach (self::TEXT_ROLES as $role) {
                foreach (self::GROUNDS as $ground) {
                    $this->assertContrast($role, $ground, self::TEXT, $theme);
                }
            }
        }
    }

    /**
     * Pills and bands: a soft tint behind its own text step. These are the pairs a price
     * change, a warning callout and the Soon marker on a disabled control use.
     */
    public function test_the_tinted_pairs_are_readable_in_both_themes(): void
    {
        $pairs = [
            ['--text-up', '--surface-up-soft'],
            ['--text-down', '--surface-down-soft'],
            ['--text-warn', '--warn-100'],
            ['--text-brand', '--surface-brand-soft'],
            ['--text-brand', '--amber-50'],
            // The inverted band (pledge band, current page in the pager, active filter chip).
            // Its ground is --ink-900, which is near-black in light mode and white in dark, so
            // both of its text roles have to flip with it rather than staying a fixed grey.
            ['--text-inverse', '--ink-900'],
            ['--text-inverse-muted', '--ink-900'],
        ];

        foreach (['light', 'dark'] as $theme) {
            foreach ($pairs as [$text, $ground]) {
                $this->assertContrast($text, $ground, self::TEXT, $theme);
            }
        }
    }

    /**
     * The primary button hardcodes its label colour, because amber-500 is the one token that
     * does not move between themes and ink-900 does.
     */
    public function test_the_primary_button_label_is_readable_on_amber(): void
    {
        foreach (['light', 'dark'] as $theme) {
            $ratio = $this->ratio('#0E0F0C', $this->resolve('--amber-500', $theme));

            $this->assertGreaterThanOrEqual(
                self::TEXT,
                $ratio,
                "The .afmc-btn--primary label is {$ratio}:1 on --amber-500 in {$theme} mode."
            );
        }
    }

    /**
     * An interactive glyph carries meaning, so it needs 3:1 even though it is not text. This is
     * why an active watch star is --amber-600 and not --amber-500.
     */
    public function test_an_interactive_glyph_clears_the_non_text_floor(): void
    {
        foreach (['light', 'dark'] as $theme) {
            foreach (['--surface-card', '--surface-sunken'] as $ground) {
                $this->assertContrast('--amber-600', $ground, self::GRAPHIC, $theme);
            }
        }
    }

    /**
     * --text-disabled is 2.4:1 by design: there is no value light enough to read as inactive
     * that also clears the floor for a control. It is a non-text token, so a view that paints a
     * label with it has picked the wrong one, and the state belongs in a Soon marker instead.
     */
    public function test_the_disabled_token_stays_out_of_labels(): void
    {
        $ratio = $this->ratio(
            $this->resolve('--text-disabled', 'light'),
            $this->resolve('--surface-card', 'light'),
        );

        $this->assertLessThan(
            self::TEXT,
            $ratio,
            'If --text-disabled now clears 4.5:1, colors.css should stop calling it non-text only.'
        );

        foreach (File::allFiles(resource_path('views')) as $file) {
            $this->assertStringNotContainsString(
                'text-disabled',
                (string) file_get_contents($file->getPathname()),
                $file->getFilename() . ' paints with --text-disabled. Use --text-faint and carry '
                . 'the unavailable state with a marker, the way the Soon pill does.'
            );
        }

        $this->assertStringNotContainsString(
            'text-disabled',
            File::get(resource_path('css/afmc.css')),
            'afmc.css paints with --text-disabled, which is reserved for chart axes and rules.'
        );
    }

    /**
     * --ink-900 is near-black in light mode and white in dark, so a surface grounded on it
     * inverts with the theme and its text has to invert too. A fixed grey does not: the kit's
     * own pledge band and toast paint --ink-300 there, which is 2.6:1 once the band turns
     * white. Every colour inside such a rule has to come from a role that flips.
     */
    public function test_an_inverted_ground_paints_only_with_roles_that_flip(): void
    {
        $allowed = [
            '--text-inverse',
            '--text-inverse-muted',
            // Amber holds its value across themes, which is the point of it: 5.9:1 on the light
            // band and 3.1:1 on the white one, so it stays a glyph colour rather than a label.
            '--amber-500',
        ];

        $rules = $this->rules();
        $grounds = [];

        foreach ($rules as [$selector, $body]) {
            if (str_contains($body, 'background: var(--ink-900)')) {
                $grounds[] = $selector;
            }
        }

        $this->assertNotEmpty($grounds, 'No rule grounds itself on --ink-900 any more, so this test is measuring nothing.');

        foreach ($rules as [$selector, $body]) {
            foreach ($grounds as $ground) {
                if (! str_starts_with($selector, $ground)) {
                    continue;
                }

                preg_match_all('/(?:^|[;{\s])color:\s*var\((--[a-z0-9-]+)\)/', $body, $matches);

                foreach ($matches[1] as $token) {
                    $this->assertContains(
                        $token,
                        $allowed,
                        "{$selector} sits on an --ink-900 ground and paints with {$token}, which does "
                        . 'not flip with the theme. Use --text-inverse or --text-inverse-muted.'
                    );
                }
            }
        }
    }

    /**
     * Selector and declaration block for every rule in afmc.css, comments stripped. Rules
     * nested in a media query come through on their own, which is what this needs.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function rules(): array
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', File::get(resource_path('css/afmc.css')));

        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER);

        return array_map(
            fn (array $match): array => [trim((string) preg_replace('/\s+/', ' ', $match[1])), $match[2]],
            $matches,
        );
    }

    private function assertContrast(string $text, string $ground, float $floor, string $theme): void
    {
        $ratio = $this->ratio($this->resolve($text, $theme), $this->resolve($ground, $theme));

        $this->assertGreaterThanOrEqual(
            $floor,
            $ratio,
            "{$text} on {$ground} is {$ratio}:1 in {$theme} mode, under the {$floor}:1 floor."
        );
    }

    private function ratio(string $foreground, string $background): float
    {
        $first = $this->luminance($foreground);
        $second = $this->luminance($background);

        $lighter = max($first, $second);
        $darker = min($first, $second);

        return round(($lighter + 0.05) / ($darker + 0.05), 2);
    }

    private function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');

        $channels = array_map(function (string $pair): float {
            $value = hexdec($pair) / 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, str_split($hex, 2));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /**
     * Follows a token through however many `var()` hops to the hex it ends at. A dark-theme
     * token that is not redeclared keeps the light declaration, which is the whole point of the
     * two blocks: the semantic name stays, the value under it moves.
     */
    private function resolve(string $token, string $theme, int $depth = 0): string
    {
        $this->assertLessThan(10, $depth, "Token {$token} loops through var() references.");

        $themed = $this->tokens($theme);
        $light = $this->tokens('light');
        $value = trim($themed[$token] ?? $light[$token] ?? '');

        $this->assertNotSame('', $value, "Token {$token} is not defined for the {$theme} theme.");

        if (preg_match('/^var\((--[a-z0-9-]+)\)$/', $value, $match) === 1) {
            return $this->resolve($match[1], $theme, $depth + 1);
        }

        $this->assertMatchesRegularExpression(
            '/^#[0-9A-Fa-f]{6}$/',
            $value,
            "Token {$token} resolves to {$value}, which this test cannot measure."
        );

        return $value;
    }

    /**
     * @return array<string, string>
     */
    private function tokens(string $theme): array
    {
        static $cache = [];

        if (isset($cache[$theme])) {
            return $cache[$theme];
        }

        $css = File::get(resource_path('css/design-system/colors.css'));
        $selector = $theme === 'dark' ? '[data-theme="dark"]' : ':root';
        $start = strpos($css, $selector);

        $this->assertNotFalse($start, "colors.css has no {$selector} block.");

        $open = strpos($css, '{', $start);
        $depth = 0;
        $end = $open;

        for ($i = $open, $length = strlen($css); $i < $length; $i++) {
            if ($css[$i] === '{') {
                $depth++;
            } elseif ($css[$i] === '}') {
                $depth--;

                if ($depth === 0) {
                    $end = $i;

                    break;
                }
            }
        }

        $body = (string) preg_replace('#/\*.*?\*/#s', '', substr($css, $open + 1, $end - $open - 1));

        preg_match_all('/(--[a-z0-9-]+)\s*:\s*([^;]+);/', $body, $matches, PREG_SET_ORDER);

        $tokens = [];
        foreach ($matches as $match) {
            $tokens[$match[1]] = trim($match[2]);
        }

        return $cache[$theme] = $tokens;
    }
}
