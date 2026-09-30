<?php

declare(strict_types=1);

namespace SilverShop\CurrencyFormat\Tests;

use SilverShop\CurrencyFormat\LocaleCurrency;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\i18n\i18n;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Covers LocaleCurrency::Nice(): defer-to-parent when nothing is selected (non-interference), locale-aware
 * formatting for a chosen currency, currency-correct fraction digits, and the narrow-symbol / ISO-code display
 * modes.
 */
class LocaleCurrencyTest extends SapphireTest
{
    protected $usesDatabase = true;

    private function nice(float $value, ?string $currency, string $display, string $locale): string
    {
        $config = SiteConfig::current_site_config();
        $config->BaseCurrency = $currency;
        $config->CurrencyDisplay = $display;
        $config->write();

        i18n::set_locale($locale);

        $field = LocaleCurrency::create('Price');
        $field->setValue($value);

        return $field->Nice();
    }

    public function testDefersToParentWhenNoCurrencySelected(): void
    {
        // Stock silvershop formatting: symbol '$', 2 decimals, no locale involvement.
        $this->assertSame('$1,234.50', $this->nice(1234.5, null, 'symbol', 'en_US'));
        $this->assertSame('$1,234.50', $this->nice(1234.5, '', 'symbol', 'nl_NL'));
    }

    public function testLocaleAwareSymbol(): void
    {
        if (!class_exists(\NumberFormatter::class)) {
            $this->markTestSkipped('ext-intl not installed');
        }

        $nl = $this->nice(1234.5, 'EUR', 'symbol', 'nl_NL');
        $this->assertStringContainsString('€', $nl);
        $this->assertStringContainsString('1.234,50', $nl);

        $de = $this->nice(1234.5, 'EUR', 'symbol', 'de_DE');
        $this->assertStringContainsString('€', $de);
        $this->assertStringContainsString('1.234,50', $de);
    }

    public function testCurrencyFractionDigits(): void
    {
        if (!class_exists(\NumberFormatter::class)) {
            $this->markTestSkipped('ext-intl not installed');
        }

        // JPY has no minor unit.
        $jpy = $this->nice(1234, 'JPY', 'symbol', 'en_US');
        $this->assertStringContainsString('1,234', $jpy);
        $this->assertStringNotContainsString('1,234.00', $jpy);
    }

    public function testNarrowSymbolAndIsoCode(): void
    {
        if (!class_exists(\NumberFormatter::class)) {
            $this->markTestSkipped('ext-intl not installed');
        }

        // Narrow forces the plain symbol even where the locale would disambiguate (nl_NL renders USD as "US$").
        $narrow = $this->nice(1234.5, 'USD', 'narrowSymbol', 'nl_NL');
        $this->assertStringContainsString('$', $narrow);
        $this->assertStringNotContainsString('US$', $narrow);

        $code = $this->nice(1234.5, 'USD', 'code', 'nl_NL');
        $this->assertStringContainsString('USD', $code);
    }

    public function testNegativeUsesNegativeFormat(): void
    {
        if (!class_exists(\NumberFormatter::class)) {
            $this->markTestSkipped('ext-intl not installed');
        }

        $negative = $this->nice(-9.95, 'EUR', 'symbol', 'nl_NL');
        $this->assertStringContainsString('negative', $negative);
        $this->assertStringContainsString('9,95', $negative);
    }
}
