<?php

declare(strict_types=1);

namespace SilverShop\CurrencyFormat;

use SilverShop\ORM\FieldType\ShopCurrency;
use SilverStripe\i18n\i18n;
use Symfony\Component\Intl\Currencies;

/**
 * Drop-in replacement for silvershop's `Currency` DBField that renders prices for the CMS-selected base currency
 * using the active locale (ICU / ext-intl): correct symbol, placement, spacing, grouping/decimal separators and
 * the currency's own fraction digits (e.g. nl_NL → "€ 9,95", de_DE → "9,95 €", JPY → no decimals).
 *
 * When no base currency is selected in the CMS it defers entirely to the parent, so installs without a selection
 * render exactly as silvershop/core does.
 */
class LocaleCurrency extends ShopCurrency
{
    /**
     * Per-request cache of configured NumberFormatter instances, keyed by "locale|display|currency". Constructing a
     * NumberFormatter is ~30× the cost of a format call and prices render many times per page, so build once and
     * reuse. Keyed by locale so it stays correct if the locale changes within the request.
     *
     * @var array<string, \NumberFormatter|false>
     */
    private static array $formatters = [];

    public function Nice(): string
    {
        // "Free" (zero) handling is currency-agnostic — let the parent own it.
        if (self::config()->get('use_free_text') && $this->value == 0) {
            return parent::Nice();
        }

        ['currency' => $currency, 'display' => $display] = CurrencyConfigExtension::resolveCurrencyFormat();

        // Nothing chosen: standard core formatting, byte-identical to a stock install.
        if ($currency === '') {
            return parent::Nice();
        }

        $formatted = $this->formatWithCurrency(abs((float) $this->value), $currency, $display);

        if ($this->value < 0) {
            return sprintf(self::config()->get('negative_value_format'), $formatted);
        }

        return $formatted;
    }

    /**
     * Format an amount as the given ISO 4217 currency for the active locale. $display is one of the
     * Intl.NumberFormat currencyDisplay values: 'symbol' (locale default, may disambiguate e.g. US$), 'narrowSymbol'
     * (force the plain symbol, e.g. $) or 'code' (the ISO code, e.g. USD). Falls back to a simple "symbol amount"
     * using symfony/intl data when ext-intl is unavailable.
     */
    protected function formatWithCurrency(float $amount, string $currency, string $display): string
    {
        $formatter = $this->numberFormatter($currency, $display);
        if ($formatter instanceof \NumberFormatter) {
            // 'symbol' keeps ICU's own currency symbol (formatCurrency); narrow/code pre-configure the formatter.
            $formatted = $display === 'symbol'
                ? $formatter->formatCurrency($amount, $currency)
                : $formatter->format($amount);
            if ($formatted !== false) {
                return $formatted;
            }
        }

        // No ext-intl: a reasonable, non-localised default.
        $glyph = $display === 'code' ? $currency : Currencies::getSymbol($currency);

        return $glyph . "\u{00a0}" . number_format(
            $amount,
            self::config()->get('decimals'),
            self::config()->get('decimal_delimiter'),
            self::config()->get('thousand_delimiter')
        );
    }

    /**
     * Build (once) and return a NumberFormatter for the active locale + display mode + currency, or false when
     * ext-intl is unavailable. For narrow/code the symbol is substituted and the currency's fraction digits set,
     * because formatCurrency() ignores a custom symbol — so those modes are driven through format() instead.
     */
    protected function numberFormatter(string $currency, string $display): \NumberFormatter|false
    {
        if (!class_exists(\NumberFormatter::class)) {
            return false;
        }

        $locale = i18n::get_locale();
        $key = $locale . '|' . $display . '|' . $currency;

        if (!array_key_exists($key, self::$formatters)) {
            $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);

            if ($display !== 'symbol') {
                $glyph = $display === 'code' ? $currency : Currencies::getSymbol($currency);
                $formatter->setSymbol(\NumberFormatter::CURRENCY_SYMBOL, $glyph);
                $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, Currencies::getFractionDigits($currency));
            }

            self::$formatters[$key] = $formatter;
        }

        return self::$formatters[$key];
    }
}
