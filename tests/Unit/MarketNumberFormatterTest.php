<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\MarketData\MarketNumberFormatter;
use PHPUnit\Framework\TestCase;

class MarketNumberFormatterTest extends TestCase
{
    public function test_formats_large_money_values(): void
    {
        $this->assertSame('$1.50T', MarketNumberFormatter::money(1_500_000_000_000));
        $this->assertSame('$2.00B', MarketNumberFormatter::money(2_000_000_000));
        $this->assertSame('—', MarketNumberFormatter::money(null));
    }

    public function test_formats_percent(): void
    {
        $this->assertSame('1.23%', MarketNumberFormatter::percent(1.234));
        $this->assertSame('—', MarketNumberFormatter::percent(null));
    }
}
