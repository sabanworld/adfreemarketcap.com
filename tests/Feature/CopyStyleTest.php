<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Guards the writing rules in AGENTS.md ("Copy and writing style"): no em dashes
 * in prose, and none of the stock phrasings that read as machine-written.
 */
class CopyStyleTest extends TestCase
{
    /**
     * Em dash as a placeholder for a missing figure is typography, not prose,
     * so those two shapes are removed before a file is checked.
     *
     * @var list<string>
     */
    private const PLACEHOLDERS = ["'—'", '>—<'];

    /**
     * @var list<string>
     */
    private const BANNED_PHRASES = [
        'says so',
        'seamless',
        'effortless',
        'dive in',
        "Let's ",
        'needless to say',
        'ranking cannot be bought',
        'rankings cannot be bought',
        'cannot be bought',
        // Defensive anti-payola asides bolted onto widgets, cookie bars, or notes.
        // Name the commission or partnership and stop; do not reassure about rankings.
        'not a paid placement',
        'not paid placements',
        'none of them are paid placements',
        'nothing in the rankings',
        'changes nothing about the rankings',
        'changes nothing about the pages',
        'nobody pays to appear',
        'nobody can pay to',
        'rankings and the rest of the site keep working',
        'rankings never change',
        'rankings changes because',
        // Product copy names the person (config('company.person')); legal pages
        // name the operating company. "The creator" belongs to neither voice.
        'our creator',
        'the creator',
    ];

    public function test_no_prose_uses_an_em_dash(): void
    {
        foreach ($this->copyFiles() as $path) {
            $body = $this->withoutCodeSpans(
                str_replace(self::PLACEHOLDERS, '', (string) file_get_contents($path))
            );

            $this->assertStringNotContainsString(
                '—',
                $body,
                $this->relative($path) . ' uses an em dash. Use a comma, a colon, parentheses, or two sentences.'
            );
        }
    }

    public function test_no_copy_uses_the_banned_phrases(): void
    {
        foreach ($this->copyFiles() as $path) {
            // The rule itself has to be able to name the phrases it forbids.
            if (str_ends_with($path, 'AGENTS.md') || str_ends_with($path, 'CopyStyleTest.php')) {
                continue;
            }

            $body = $this->withoutCodeSpans((string) file_get_contents($path));

            foreach (self::BANNED_PHRASES as $phrase) {
                $this->assertStringNotContainsStringIgnoringCase(
                    $phrase,
                    $body,
                    $this->relative($path) . " uses \"{$phrase}\", which AGENTS.md rules out."
                );
            }
        }
    }

    /**
     * Text inside `backticks` is a doc quoting the character or phrase it bans,
     * so it is dropped before a file is checked.
     */
    private function withoutCodeSpans(string $body): string
    {
        return (string) preg_replace('/`[^`]*`/', '', $body);
    }

    /**
     * @return list<string>
     */
    private function copyFiles(): array
    {
        $files = array_merge(
            File::allFiles(resource_path('views')),
            File::allFiles(resource_path('css')),
            File::allFiles(lang_path()),
            [base_path('README.md'), base_path('AGENTS.md')],
            File::allFiles(base_path('docs')),
        );

        return array_values(array_map(
            static fn ($file): string => is_string($file) ? $file : $file->getPathname(),
            $files,
        ));
    }

    private function relative(string $path): string
    {
        return str_replace(base_path() . '/', '', $path);
    }
}
