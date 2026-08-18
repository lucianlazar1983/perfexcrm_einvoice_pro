<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Keeps the small set of code-list decisions made by the Perfex adapter in one place.
 */
final class Einvoice_pro_codes
{
    private const CURRENCIES = [
        'AED', 'AUD', 'BGN', 'BRL', 'CAD', 'CHF', 'CNY', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD',
        'HRK', 'HUF', 'ILS', 'INR', 'ISK', 'JPY', 'KRW', 'MDL', 'MXN', 'NOK', 'NZD', 'PLN',
        'RON', 'RSD', 'SEK', 'SGD', 'TRY', 'UAH', 'USD', 'ZAR',
    ];

    private const COUNTRIES = [
        'AD', 'AE', 'AL', 'AM', 'AR', 'AT', 'AU', 'AZ', 'BA', 'BE', 'BG', 'BR', 'BY', 'CA',
        'CH', 'CL', 'CN', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GB', 'GE', 'GR',
        'HK', 'HR', 'HU', 'IE', 'IL', 'IN', 'IS', 'IT', 'JP', 'KR', 'LI', 'LT', 'LU', 'LV',
        'MC', 'MD', 'ME', 'MK', 'MT', 'MX', 'NL', 'NO', 'NZ', 'PL', 'PT', 'RO', 'RS', 'SE',
        'SG', 'SI', 'SK', 'TR', 'UA', 'US', 'VA', 'ZA',
    ];

    private const UNITS = [
        'C62', 'DAY', 'E48', 'H87', 'HUR', 'KGM', 'KMT', 'LTR', 'MIN', 'MON', 'MTK', 'MTQ',
        'MTR', 'NAR', 'TNE', 'WEE',
    ];

    private const UNIT_ALIASES = [
        'buc' => 'H87',
        'bucata' => 'H87',
        'bucati' => 'H87',
        'piece' => 'H87',
        'pieces' => 'H87',
        'pcs' => 'H87',
        'unit' => 'H87',
        'units' => 'H87',
        'serviciu' => 'E48',
        'servicii' => 'E48',
        'service' => 'E48',
        'ora' => 'HUR',
        'ore' => 'HUR',
        'hour' => 'HUR',
        'hours' => 'HUR',
        'h' => 'HUR',
        'zi' => 'DAY',
        'zile' => 'DAY',
        'day' => 'DAY',
        'days' => 'DAY',
        'luna' => 'MON',
        'luni' => 'MON',
        'month' => 'MON',
        'months' => 'MON',
        'kg' => 'KGM',
        'kilogram' => 'KGM',
        'kilograme' => 'KGM',
        'm' => 'MTR',
        'metru' => 'MTR',
        'metri' => 'MTR',
        'm2' => 'MTK',
        'mp' => 'MTK',
        'm3' => 'MTQ',
        'mc' => 'MTQ',
        'l' => 'LTR',
        'litru' => 'LTR',
        'litri' => 'LTR',
        'km' => 'KMT',
        'kilometru' => 'KMT',
        'kilometri' => 'KMT',
        'tona' => 'TNE',
        'tone' => 'TNE',
    ];

    private const ROMANIAN_SUBDIVISIONS = [
        'alba' => 'AB', 'arad' => 'AR', 'arges' => 'AG', 'bacau' => 'BC', 'bihor' => 'BH',
        'bistrita-nasaud' => 'BN', 'botosani' => 'BT', 'braila' => 'BR', 'brasov' => 'BV',
        'bucuresti' => 'B', 'buzau' => 'BZ', 'calarasi' => 'CL', 'caras-severin' => 'CS',
        'cluj' => 'CJ', 'constanta' => 'CT', 'covasna' => 'CV', 'dambovita' => 'DB',
        'dolj' => 'DJ', 'galati' => 'GL', 'giurgiu' => 'GR', 'gorj' => 'GJ', 'harghita' => 'HR',
        'hunedoara' => 'HD', 'ialomita' => 'IL', 'iasi' => 'IS', 'ilfov' => 'IF',
        'maramures' => 'MM', 'mehedinti' => 'MH', 'mures' => 'MS', 'neamt' => 'NT', 'olt' => 'OT',
        'prahova' => 'PH', 'salaj' => 'SJ', 'satu mare' => 'SM', 'sibiu' => 'SB',
        'suceava' => 'SV', 'teleorman' => 'TR', 'timis' => 'TM', 'tulcea' => 'TL',
        'valcea' => 'VL', 'vaslui' => 'VS', 'vrancea' => 'VN',
    ];

    private const IBAN_LENGTHS = [
        'AD' => 24, 'AE' => 23, 'AL' => 28, 'AT' => 20, 'AZ' => 28, 'BA' => 20, 'BE' => 16,
        'BG' => 22, 'BY' => 28, 'CH' => 21, 'CY' => 28, 'CZ' => 24, 'DE' => 22, 'DK' => 18,
        'EE' => 20, 'ES' => 24, 'FI' => 18, 'FR' => 27, 'GB' => 22, 'GE' => 22, 'GR' => 27,
        'HR' => 21, 'HU' => 28, 'IE' => 22, 'IL' => 23, 'IS' => 26, 'IT' => 27, 'LI' => 21,
        'LT' => 20, 'LU' => 20, 'LV' => 21, 'MC' => 27, 'MD' => 24, 'ME' => 22, 'MK' => 19,
        'MT' => 31, 'NL' => 18, 'NO' => 15, 'PL' => 28, 'PT' => 25, 'RO' => 24, 'RS' => 22,
        'SE' => 24, 'SI' => 19, 'SK' => 24, 'TR' => 26, 'UA' => 29,
    ];

    /**
     * Accepts a currency only when it belongs to the module's reviewed ISO 4217 subset.
     */
    public static function currency(string $value): string
    {
        $value = strtoupper(trim($value));
        if (!in_array($value, self::CURRENCIES, true)) {
            throw new Einvoice_pro_validation_exception('currency.unsupported', 'The invoice currency is not supported.');
        }

        return $value;
    }

    /**
     * Accepts a country only when it belongs to the module's reviewed ISO 3166-1 subset.
     */
    public static function country(string $value): string
    {
        $value = strtoupper(trim($value));
        if (!in_array($value, self::COUNTRIES, true)) {
            throw new Einvoice_pro_validation_exception('country.unsupported', 'The country code is not supported.');
        }

        return $value;
    }

    /**
     * Maps common Perfex unit labels to reviewed UN/ECE Recommendation 20 codes.
     */
    public static function unit(string $value): string
    {
        $value = trim($value);
        $code = strtoupper($value);
        if (in_array($code, self::UNITS, true)) {
            return $code;
        }

        $alias = self::plainText($value);
        if (isset(self::UNIT_ALIASES[$alias])) {
            return self::UNIT_ALIASES[$alias];
        }

        throw new Einvoice_pro_validation_exception(
            'unit.unsupported',
            'An invoice line uses a unit that has no reviewed UN/ECE mapping.'
        );
    }

    /**
     * Normalizes Romanian county names and official codes without inventing a subdivision.
     */
    public static function subdivision(string $country, string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if ($country !== 'RO') {
            return $value;
        }

        $code = strtoupper($value);
        if (preg_match('/^RO-(?:B|[A-Z]{2})$/D', $code)) {
            return $code;
        }
        if (preg_match('/^(?:B|[A-Z]{2})$/D', $code)) {
            return 'RO-' . $code;
        }

        $name = self::plainText($value);
        if (isset(self::ROMANIAN_SUBDIVISIONS[$name])) {
            return 'RO-' . self::ROMANIAN_SUBDIVISIONS[$name];
        }

        throw new Einvoice_pro_validation_exception(
            'subdivision.unsupported',
            'The Romanian county could not be mapped to an official subdivision code.'
        );
    }

    /**
     * Checks an IBAN with the standard rearrangement and mod-97 calculation.
     */
    public static function iban(string $value): string
    {
        $iban = strtoupper(preg_replace('/\s+/', '', trim($value)) ?? '');
        if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/D', $iban)) {
            throw new Einvoice_pro_validation_exception('payment.iban', 'The configured IBAN is not valid.');
        }
        $country = substr($iban, 0, 2);
        if (!isset(self::IBAN_LENGTHS[$country]) || strlen($iban) !== self::IBAN_LENGTHS[$country]) {
            throw new Einvoice_pro_validation_exception('payment.iban', 'The configured IBAN is not valid.');
        }

        $rearranged = substr($iban, 4) . substr($iban, 0, 4);
        $remainder = 0;
        for ($index = 0, $length = strlen($rearranged); $index < $length; $index++) {
            $character = $rearranged[$index];
            $digits = ctype_alpha($character) ? (string) (ord($character) - 55) : $character;
            for ($digitIndex = 0, $digitLength = strlen($digits); $digitIndex < $digitLength; $digitIndex++) {
                $remainder = (($remainder * 10) + (int) $digits[$digitIndex]) % 97;
            }
        }

        if ($remainder !== 1) {
            throw new Einvoice_pro_validation_exception('payment.iban', 'The configured IBAN is not valid.');
        }

        return $iban;
    }

    /**
     * Produces a lowercase ASCII lookup key for user-entered labels.
     */
    private static function plainText(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = strtr($value, [
            'ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't',
        ]);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return $value;
    }
}
