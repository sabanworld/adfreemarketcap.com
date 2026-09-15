<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Every control a thumb has to hit needs a size floor, and the floor has to survive the
 * cascade. Two things kept eating it: an element selector like `button` loses to any class
 * that sets its own min-height, and most controls here open with `all: unset`, which resets
 * min-height at class specificity. A details button shipped 17px tall because of that, which
 * is readable and not tappable.
 *
 * So the rule this test enforces is that a control declares its own floor, rather than hoping
 * one reaches it: 44px, the size the design system asks for, with a short list of exceptions
 * that each stay above the 24px minimum in WCAG 2.2 success criterion 2.5.8.
 *
 * `cursor: pointer` is how a rule tells this test it is a control. An anchor gets that cursor
 * from the browser, so a standalone link class (`.afmc-link`, the miners block height) declares
 * it anyway to opt into the audit. A link inside a sentence is deliberately not a control:
 * criterion 2.5.8 exempts a target constrained by the line height of the text around it.
 */
class TouchTargetTest extends TestCase
{
    private const FLOOR = 44;

    /**
     * WCAG 2.2 level AA, criterion 2.5.8. resources/views/legal/accessibility.blade.php claims
     * that level, so nothing may sit below this, exception or not.
     */
    private const ABSOLUTE_FLOOR = 24;

    /**
     * Controls allowed under 44px, with the size they are allowed and why. Adding a line here
     * is a decision about a real trade-off, which is the point of making it explicit.
     *
     * @var array<string, array{0: int, 1: string}>
     */
    private const EXCEPTIONS = [
        '.afmc-table th button' => [38, 'a sort header inside a table pan region: 44px rows turn a ranking into a stack of cards'],
        '.afmc-table__sortpair > button' => [38, 'the paired sort header, same reason'],
        '.afmc-currency__filter-clear' => [32, 'sits inside a 44px text field, which is itself the target'],
    ];

    public function test_every_interactive_class_declares_a_touch_target_floor(): void
    {
        $rules = $this->rules(File::get(resource_path('css/afmc.css')));

        $interactive = [];
        $floors = [];

        foreach ($rules as $rule) {
            // A width-based media query says nothing about a floor on a touch screen, so only
            // the unconditional rules and the coarse-pointer block count here.
            $counts = $rule['context'] === [] || in_array('@media (pointer: coarse)', $rule['context'], true);

            foreach ($rule['selectors'] as $selector) {
                if (str_contains($rule['declarations'], 'cursor: pointer')) {
                    $interactive[$selector] = true;
                }

                if (! $counts) {
                    continue;
                }

                foreach (['min-height', 'height'] as $property) {
                    if (preg_match('/(?:^|;|\s)' . $property . ':\s*(\d+)px/', $rule['declarations'], $match)) {
                        $floors[$selector] = max($floors[$selector] ?? 0, (int) $match[1]);
                    }
                }
            }
        }

        $this->assertNotEmpty($interactive, 'Found no interactive rules, so the parse is wrong.');

        foreach (array_keys($interactive) as $selector) {
            $floor = $floors[$selector] ?? 0;
            [$allowed, $reason] = self::EXCEPTIONS[$selector] ?? [self::FLOOR, ''];

            $this->assertGreaterThanOrEqual(
                $allowed,
                $floor,
                "{$selector} is an interactive control with a {$floor}px floor. Give it a "
                . self::FLOOR . 'px min-height (the coarse-pointer block at the end of afmc.css '
                . 'is where the rest live), or add it to ' . self::class . '::EXCEPTIONS with the reason.'
            );

            $this->assertGreaterThanOrEqual(
                self::ABSOLUTE_FLOOR,
                $floor,
                "{$selector} is below the 24px WCAG 2.2 minimum, which the accessibility statement claims."
            );

            if ($reason !== '') {
                $this->assertLessThan(
                    self::FLOOR,
                    $allowed,
                    "{$selector} is listed as an exception but is allowed the full floor. Drop the exception."
                );
            }
        }
    }

    /**
     * The bug that started this: padding lived only in the size modifiers, so the base class
     * rendered a control the height of one line of text.
     */
    public function test_the_button_class_is_a_usable_control_without_a_size_modifier(): void
    {
        $rules = $this->rules(File::get(resource_path('css/afmc.css')));

        $base = collect($rules)->first(
            fn (array $rule) => $rule['selectors'] === ['.afmc-btn'] && $rule['context'] === []
        );

        $this->assertNotNull($base, 'Lost the .afmc-btn rule.');
        $this->assertMatchesRegularExpression('/padding:\s*\d/', $base['declarations']);
        $this->assertMatchesRegularExpression('/min-height:\s*(3[2-9]|[4-9]\d)px/', $base['declarations']);
    }

    /**
     * The exceptions are scoped to a table pan region or a text field. If a phone-only surface
     * ever borrows one, the reason stops being true, so the list stays short by construction.
     */
    public function test_the_phone_only_surfaces_use_no_exception(): void
    {
        $list = File::get(resource_path('views/components/afmc/market-list.blade.php'));
        $exchanges = File::get(resource_path('views/components/afmc/exchange-list.blade.php'));

        foreach (array_keys(self::EXCEPTIONS) as $selector) {
            $class = ltrim(explode(' ', $selector)[0], '.');

            $this->assertStringNotContainsString($class, $list);
            $this->assertStringNotContainsString($class, $exchanges);
        }
    }

    /**
     * Flat CSS parse: selector, declarations, and the at-rules the rule sits inside. The file
     * uses no nested rules, so a plain rule runs to its first closing brace.
     *
     * @return list<array{selectors: list<string>, declarations: string, context: list<string>}>
     */
    private function rules(string $css): array
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);

        $rules = [];
        $context = [];
        $buffer = '';

        for ($i = 0, $length = strlen($css); $i < $length; $i++) {
            $character = $css[$i];

            if ($character === '{') {
                $prelude = trim(preg_replace('/\s+/', ' ', $buffer) ?? '');
                $buffer = '';

                if (str_starts_with($prelude, '@')) {
                    $context[] = $prelude;

                    continue;
                }

                $end = strpos($css, '}', $i);

                if ($end === false) {
                    break;
                }

                $rules[] = [
                    'selectors' => array_map('trim', explode(',', $prelude)),
                    'declarations' => trim(substr($css, $i + 1, $end - $i - 1)),
                    'context' => $context,
                ];

                $i = $end;

                continue;
            }

            if ($character === '}') {
                array_pop($context);
                $buffer = '';

                continue;
            }

            $buffer .= $character;
        }

        return $rules;
    }
}
