<?php

declare(strict_types=1);

namespace SilverShop\CurrencyFormat;

use SilverShop\Extension\ShopConfigExtension;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\FieldType\DBField;
use Symfony\Component\Intl\Currencies;

/**
 * Applied to {@link \SilverStripe\SiteConfig\SiteConfig}. Adds a base-currency selector and a display style to
 * Settings > Shop, so single-currency shops can set their currency in the CMS instead of the `base_currency` YAML
 * config. Blank = the system/YAML default (non-invasive for installs already configured that way).
 *
 * @property \SilverStripe\SiteConfig\SiteConfig $owner
 * @property ?string $BaseCurrency
 * @property ?string $CurrencyDisplay
 */
class CurrencyConfigExtension extends Extension
{
    private static array $db = [
        'BaseCurrency' => 'Varchar(3)',
        'CurrencyDisplay' => "Enum('symbol,narrowSymbol,code', 'symbol')",
    ];

    public function updateCMSFields(FieldList $fields): void
    {
        $default = (string) ShopConfigExtension::config()->get('base_currency');

        $currency = DropdownField::create(
            'BaseCurrency',
            _t(self::class . '.BaseCurrency', 'Currency'),
            self::getCurrencyOptions()
        )
            ->setEmptyString(_t(
                self::class . '.BaseCurrencyDefault',
                'System default ({currency})',
                ['currency' => $default]
            ))
            ->setDescription(_t(
                self::class . '.BaseCurrencyDesc',
                'The shop base currency. Leave blank to use the system default (the base_currency YAML config).'
            ));

        $display = DropdownField::create(
            'CurrencyDisplay',
            _t(self::class . '.CurrencyDisplay', 'Currency display'),
            [
                'symbol' => _t(self::class . '.DisplaySymbol', 'Symbol — locale default (e.g. US$, JP¥)'),
                'narrowSymbol' => _t(self::class . '.DisplayNarrow', 'Narrow symbol (e.g. $, ¥)'),
                'code' => _t(self::class . '.DisplayCode', 'ISO code (e.g. USD)'),
            ]
        )->setDescription(_t(
            self::class . '.CurrencyDisplayDesc',
            'How the currency is shown in prices. Only applies when a currency is selected above.'
        ));

        // A live, read-only sample of how a price renders with the saved settings above.
        $example = ReadonlyField::create(
            'CurrencyDisplayExample',
            _t(self::class . '.CurrencyExample', 'Currency display example'),
            DBField::create_field('Currency', 1234.56)->Nice()
        )->setDescription(_t(
            self::class . '.CurrencyExampleDesc',
            'A sample amount (1234.56) rendered with the currency and display settings above. Updates after you save.'
        ));

        // Slot into the Main sub-tab that silvershop/core builds under Settings > Shop; fall back to Root.Main when
        // the Shop tab is absent (e.g. a SiteConfig without silvershop/core's settings). Mirrors how the invoicing
        // and downloads modules target this tabset.
        $tab = $fields->fieldByName('Root.Shop') ? 'Root.Shop.ShopTabs.Main' : 'Root.Main';
        $fields->addFieldToTab($tab, $currency);
        $fields->addFieldToTab($tab, $display);
        $fields->addFieldToTab($tab, $example);
    }

    /**
     * Make the CMS-selected currency authoritative via core's `updateSiteCurrency` hook, so orders, conversion and
     * display all agree. Blank selection leaves core's `base_currency` default untouched.
     */
    public function updateSiteCurrency(string &$currency): void
    {
        if ($chosen = (string) $this->getOwner()->BaseCurrency) {
            $currency = $chosen;
        }
    }

    /**
     * Currency options as "ISO - symbol (name)", e.g. "EUR - € (Euro)", from symfony/intl. Restricted to the
     * shop's `supported_currencies` when set, otherwise every ISO 4217 currency (the CMS dropdown is searchable).
     *
     * @return array<string, string>
     */
    public static function getCurrencyOptions(): array
    {
        $codes = ShopConfigExtension::config()->get('supported_currencies') ?: Currencies::getCurrencyCodes();

        $options = [];
        foreach ($codes as $code) {
            $options[$code] = sprintf('%s - %s (%s)', $code, Currencies::getSymbol($code), Currencies::getName($code));
        }
        asort($options);

        return $options;
    }
}
