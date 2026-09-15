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
     * --surface-inverse, which is why that pair is absent: in dark mode the token maps to
     * paper-4 and would fail, and the ink band uses the --ink-200/300 steps instead.
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
            // The ink band (pledge band, current page in the pager, active filter chip).
            ['--text-inverse', '--ink-900'],
            ['--ink-200', '--ink-900'],
            ['--ink-300', '--ink-900'],
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
            $ratio = $this->ratio('#14120E', $this->resolve('--amber-500', $theme));

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
