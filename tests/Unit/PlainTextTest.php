<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PlainText;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlainTextTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: ?string}>
     */
    public static function htmlProvider(): array
    {
        return [
            'nbsp entity' => [
                'TON is a general-purpose&nbsp;blockchain that allows developers to build apps.',
                'TON is a general-purpose blockchain that allows developers to build apps.',
            ],
            'tags and entities' => [
                '<p>Smart&amp; contract &lt;platform&gt;</p>',
                'Smart& contract <platform>',
            ],
            'empty after strip' => [
                '<p>   </p>',
                null,
            ],
        ];
    }

    #[DataProvider('htmlProvider')]
    public function test_from_html_decodes_entities_and_strips_tags(string $input, ?string $expected): void
    {
        $this->assertSame($expected, PlainText::fromHtml($input));
    }

    public function test_from_html_passes_through_null_and_empty(): void
    {
        $this->assertNull(PlainText::fromHtml(null));
        $this->assertSame('', PlainText::fromHtml(''));
    }
}
