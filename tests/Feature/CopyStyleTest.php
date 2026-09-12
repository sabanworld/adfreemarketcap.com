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
    ];

    public function test_no_prose_uses_an_em_dash(): void
    {
        foreach ($this->copyFiles() as $path) {
            $body = str_replace(self::PLACEHOLDERS, '', (string) file_get_contents($path));

            // An em dash inside `backticks` is the rule quoting the character it bans.
            $body = (string) preg_replace('/`[^`]*`/', '', $body);

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

            $body = (string) file_get_contents($path);

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
