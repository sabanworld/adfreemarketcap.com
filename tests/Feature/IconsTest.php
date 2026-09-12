<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Icons;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Tests\TestCase;

class IconsTest extends TestCase
{
    public function test_every_icon_in_the_manifest_resolves_to_one_character(): void
    {
        $codepoints = Icons::codepoints();

        $this->assertNotEmpty($codepoints);

        foreach (array_keys($codepoints) as $name) {
            $this->assertSame(1, mb_strlen(Icons::character($name)), "Icon [{$name}] is not a single character.");
        }
    }

    public function test_unknown_icons_fail_loudly(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Icons::character('definitely_not_an_icon');
    }

    public function test_icons_used_in_views_exist_in_the_subset(): void
    {
        $known = array_keys(Icons::codepoints());

        foreach (File::allFiles(resource_path('views')) as $file) {
            preg_match_all(
                '/<x-afmc\.icon[^>]*\sname="([a-z_]+)"/',
                (string) file_get_contents($file->getPathname()),
                $matches,
            );

            foreach ($matches[1] as $name) {
                $this->assertContains(
                    $name,
                    $known,
                    "{$file->getFilename()} renders icon [{$name}], which is missing from resources/fonts/material-symbols.json. Add it and run yarn icons:build."
                );
            }
        }
    }

    public function test_the_font_subset_ships_with_the_app(): void
    {
        $this->assertFileExists(resource_path('fonts/material-symbols-rounded-subset.woff2'));
    }
}
