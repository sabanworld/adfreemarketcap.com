<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\QrSvg;
use Tests\TestCase;

class QrSvgTest extends TestCase
{
    public function test_make_returns_inline_svg_without_xml_declaration(): void
    {
        $svg = QrSvg::make('bitcoin:' . config('company.donation.btc_address'), 64);

        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringNotContainsString('<?xml', $svg);
        $this->assertStringContainsString('xmlns="http://www.w3.org/2000/svg"', $svg);
    }
}
