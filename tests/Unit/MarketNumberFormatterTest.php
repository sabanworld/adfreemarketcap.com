<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Currency\DTOs\CurrencyUnit;
use App\Services\MarketData\MarketNumberFormatter;
use Tests\TestCase;

/**
 * money() resolves the visitor's display currency from the container, so this
 * needs the framework booted rather than a bare PHPUnit test case.
 */
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

    public function test_formats_in_a_given_unit(): void
    {
        $euro = new CurrencyUnit('eur', 'Euro', '€');
        $bitcoin = new CurrencyUnit('btc', 'Bitcoin', '₿', isCrypto: true, decimals: 8);
        $sats = new CurrencyUnit('sats', 'Satoshi', ' sats', symbolAfter: true, isCrypto: true, decimals: 0);

        $this->assertSame('€1.34T', MarketNumberFormatter::format(1_340_000_000_000, $euro));
        $this->assertSame('€2,181.69', MarketNumberFormatter::format(2181.6912, $euro));
        $this->assertSame('₿0.0328', MarketNumberFormatter::format(0.03281, $bitcoin));
        $this->assertSame('₿0.00001293', MarketNumberFormatter::format(0.000012934, $bitcoin));

        // Whole satoshis: the unit's own precision caps the decimals.
        $this->assertSame('1,780 sats', MarketNumberFormatter::format(1780.42, $sats));
        $this->assertSame('—', MarketNumberFormatter::format(null, $euro));
    }

    public function test_money_usd_ignores_the_session_currency(): void
    {
        session(['display_currency' => 'eur']);

        $this->assertSame('$1,500.00', MarketNumberFormatter::moneyUsd(1500));
    }
}
