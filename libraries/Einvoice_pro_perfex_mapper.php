<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Reads the supported Perfex invoice snapshot fields into the canonical document input.
 */
final class Einvoice_pro_perfex_mapper
{
    private const VAT_PREFIXES = [
        'AT' => 'AT', 'BE' => 'BE', 'BG' => 'BG', 'CY' => 'CY', 'CZ' => 'CZ', 'DE' => 'DE',
        'DK' => 'DK', 'EE' => 'EE', 'ES' => 'ES', 'FI' => 'FI', 'FR' => 'FR', 'GB' => 'GB',
        'GR' => 'EL', 'HR' => 'HR', 'HU' => 'HU', 'IE' => 'IE', 'IT' => 'IT', 'LT' => 'LT',
        'LU' => 'LU', 'LV' => 'LV', 'MT' => 'MT', 'NL' => 'NL', 'PL' => 'PL', 'PT' => 'PT',
        'RO' => 'RO', 'SE' => 'SE', 'SI' => 'SI', 'SK' => 'SK',
    ];

    /**
     * Maps one Perfex invoice and fails when the source cannot be represented without guessing.
     *
     * @param object $invoice
     * @return array<string, mixed>
     */
    public function map($invoice): array
    {
        if (!is_object($invoice) || !isset($invoice->client) || !is_object($invoice->client)) {
            throw new Einvoice_pro_validation_exception('perfex.invoice', 'The Perfex invoice snapshot is incomplete.');
        }

        $currency = $this->currency($invoice);
        $supplier = $this->supplier();
        $buyer = $this->buyer($invoice);
        [$lines, $taxBases] = $this->lines($invoice, $supplier['vat_id'] !== null);
        $discount = Einvoice_pro_decimal::normalize($this->value($invoice, 'discount_total', '0'));
        $adjustments = $this->discountAllowances($invoice, $discount, $taxBases);
        $rounding = Einvoice_pro_decimal::normalize($this->value($invoice, 'adjustment', '0'));

        $source = [
            'id' => format_invoice_number($invoice->id),
            'issue_date' => $this->date($this->value($invoice, 'date'), 'perfex.issue_date'),
            'due_date' => $this->optionalDate($this->value($invoice, 'duedate', null), 'perfex.due_date'),
            'type_code' => '380',
            'currency' => $currency,
            'notes' => $this->selectedNotes(),
            'seller' => $supplier,
            'buyer' => $buyer,
            'payment' => $this->payment(),
            'lines' => $lines,
            'allowances_charges' => $adjustments,
            'rounding_amount' => $rounding,
            'expected_totals' => [
                'line_extension' => $this->value($invoice, 'subtotal'),
                'allowance_total' => $discount,
                'tax_amount' => $this->value($invoice, 'total_tax'),
                'payable' => $this->value($invoice, 'total'),
            ],
        ];

        if ($currency !== 'RON') {
            $source['tax_currency'] = 'RON';
            $accountingVat = $this->accountingVat($invoice);
            if ($accountingVat !== null) {
                $source['tax_amount_accounting'] = $accountingVat;
            }
        }

        if (function_exists('hooks')) {
            $source = hooks()->apply_filters(
                'einvoice_pro_document_source',
                $source,
                ['invoice' => $invoice]
            );
        }
        if (!is_array($source)) {
            throw new Einvoice_pro_validation_exception('perfex.filter', 'The document source filter returned invalid data.');
        }
        if ($currency !== 'RON' && !array_key_exists('tax_amount_accounting', $source)) {
            throw new Einvoice_pro_validation_exception(
                'perfex.accounting_vat',
                'A foreign-currency invoice needs its VAT total in RON from a trusted invoice source.'
            );
        }

        return $source;
    }

    /**
     * Reads the invoice currency through Perfex's currency model.
     *
     * @param object $invoice
     */
    private function currency($invoice): string
    {
        $CI = &get_instance();
        $CI->load->model('currencies_model');
        $currency = $CI->currencies_model->get($this->value($invoice, 'currency'));
        if (!$currency || !isset($currency->name) || !is_string($currency->name)) {
            throw new Einvoice_pro_validation_exception('perfex.currency', 'The Perfex invoice currency is missing.');
        }

        return Einvoice_pro_codes::currency($currency->name);
    }

    /**
     * Reads seller identity from the established Perfex and module settings.
     *
     * @return array<string, mixed>
     */
    private function supplier(): array
    {
        $country = $this->country($this->supplierCountrySource(), 'seller.country');
        $fiscalIdentifier = $this->optionalString(get_option('company_vat'));
        $vat = $this->vatIdentifier($fiscalIdentifier, $country);
        $registration = $this->optionalString(get_option('einvoice_pro_registration_number'));

        return [
            'name' => $this->string(get_option('invoice_company_name'), 'seller.name'),
            'legal_id' => $registration ?? $fiscalIdentifier,
            'vat_id' => $vat,
            'legal_form' => $this->legalForm(),
            'phone' => $this->optionalString(get_option('invoice_company_phonenumber')),
            'address' => [
                'street' => $this->string(get_option('invoice_company_address'), 'seller.street'),
                'city' => $this->string(get_option('invoice_company_city'), 'seller.city'),
                'subdivision' => $this->optionalString(get_option('company_state')),
                'country_code' => $country,
            ],
        ];
    }

    /**
     * Reads buyer address data from the invoice snapshot instead of the current customer address.
     *
     * @param object $invoice
     * @return array<string, mixed>
     */
    private function buyer($invoice): array
    {
        $country = $this->country($this->value($invoice, 'billing_country'), 'buyer.country');
        $fiscalIdentifier = $this->optionalString($this->value($invoice->client, 'vat', null));

        return [
            'type' => 'business',
            'name' => $this->string($this->value($invoice->client, 'company'), 'buyer.name'),
            'legal_id' => $fiscalIdentifier,
            'vat_id' => $this->vatIdentifier($fiscalIdentifier, $country),
            'address' => [
                'street' => $this->string($this->value($invoice, 'billing_street'), 'buyer.street'),
                'city' => $this->string($this->value($invoice, 'billing_city'), 'buyer.city'),
                'subdivision' => $this->optionalString($this->value($invoice, 'billing_state', null)),
                'country_code' => $country,
            ],
        ];
    }

    /**
     * Maps invoice items and returns their taxable bases grouped by VAT profile.
     *
     * @param object $invoice
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, array<string, mixed>>}
     */
    private function lines($invoice, bool $sellerHasVat): array
    {
        if (!isset($invoice->items) || !is_array($invoice->items) || $invoice->items === []) {
            throw new Einvoice_pro_validation_exception('perfex.lines', 'The Perfex invoice has no items.');
        }

        $defaultUnit = $this->optionalString(get_option('einvoice_pro_default_unit_code'));
        $lines = [];
        $taxBases = [];
        foreach (array_values($invoice->items) as $index => $item) {
            if (!is_array($item) || !array_key_exists('id', $item)) {
                throw new Einvoice_pro_validation_exception('perfex.line', 'A Perfex invoice item is incomplete.');
            }

            $taxes = get_invoice_item_taxes($item['id']);
            if (!is_array($taxes) || count($taxes) > 1) {
                throw new Einvoice_pro_validation_exception(
                    'perfex.multiple_tax',
                    'Stacked taxes on one invoice line are not supported.'
                );
            }
            $tax = $this->taxProfile($taxes, $sellerHasVat, $item, $invoice);
            $quantity = $this->decimalItem($item, 'qty');
            $price = $this->decimalItem($item, 'rate');
            $net = Einvoice_pro_decimal::multiply($quantity, $price);
            $unit = $this->optionalString($item['unit'] ?? null) ?? $defaultUnit;
            if ($unit === null) {
                throw new Einvoice_pro_validation_exception(
                    'perfex.unit',
                    'An invoice item has no unit and no default unit is configured.'
                );
            }

            $line = [
                'id' => (string) ($index + 1),
                'quantity' => $quantity,
                'unit' => $unit,
                'net_amount' => $net,
                'name' => $this->string($item['description'] ?? null, 'line.name'),
                'description' => $this->optionalString($item['long_description'] ?? null),
                'price_amount' => $price,
                'tax' => $tax,
            ];
            $lines[] = $line;

            $key = $this->taxKey($tax);
            if (!isset($taxBases[$key])) {
                $taxBases[$key] = ['amount' => '0.00', 'tax' => $tax];
            }
            $taxBases[$key]['amount'] = Einvoice_pro_decimal::add($taxBases[$key]['amount'], $net);
        }

        return [$lines, $taxBases];
    }

    /**
     * Derives a safe VAT profile from a single Perfex tax record.
     *
     * @param array<int, mixed> $taxes
     * @return array<string, string>
     */
    private function taxProfile(array $taxes, bool $sellerHasVat, array $item, $invoice): array
    {
        if (function_exists('hooks')) {
            $mapped = hooks()->apply_filters(
                'einvoice_pro_line_tax_profile',
                null,
                ['taxes' => $taxes, 'item' => $item, 'invoice' => $invoice]
            );
            if ($mapped !== null) {
                if (!is_array($mapped)) {
                    throw new Einvoice_pro_validation_exception(
                        'perfex.tax_filter',
                        'The line VAT profile filter returned invalid data.'
                    );
                }

                return $mapped;
            }
        }

        if ($taxes === []) {
            if ($sellerHasVat) {
                throw new Einvoice_pro_validation_exception(
                    'perfex.zero_tax',
                    'A zero-tax line needs an explicit exempt or reverse-charge mapping.'
                );
            }

            return [
                'category' => 'O',
                'rate' => '0.00',
                'exemption_code' => 'VATEX-EU-O',
                'exemption_reason' => null,
            ];
        }

        $tax = $taxes[0];
        if (!is_array($tax) || !array_key_exists('taxrate', $tax)) {
            throw new Einvoice_pro_validation_exception('perfex.tax', 'A Perfex tax record is incomplete.');
        }
        $rate = Einvoice_pro_decimal::normalize($this->decimal($tax['taxrate'], 'perfex.tax_rate'), 2);
        if (Einvoice_pro_decimal::compare($rate, '0.00', 2) <= 0) {
            throw new Einvoice_pro_validation_exception(
                'perfex.zero_tax',
                'A zero-rate tax needs an explicit E, AE, or O category and exemption reason.'
            );
        }

        return [
            'category' => 'S',
            'rate' => $rate,
            'exemption_code' => null,
            'exemption_reason' => null,
        ];
    }

    /**
     * Allocates a before-tax discount across VAT profiles without using binary floats.
     *
     * @param object $invoice
     * @param array<string, array<string, mixed>> $taxBases
     * @return array<int, array<string, mixed>>
     */
    private function discountAllowances($invoice, string $discount, array $taxBases): array
    {
        if (Einvoice_pro_decimal::isZero($discount, 2)) {
            return [];
        }
        if (Einvoice_pro_decimal::compare($discount, '0.00', 2) < 0) {
            throw new Einvoice_pro_validation_exception('perfex.discount', 'The Perfex discount cannot be negative.');
        }

        $discountType = $this->optionalString($this->value($invoice, 'discount_type', null));
        if ($discountType !== null && $discountType !== 'before_tax') {
            throw new Einvoice_pro_validation_exception(
                'perfex.discount_type',
                'Only before-tax document discounts can be mapped safely.'
            );
        }

        $subtotal = '0.00';
        foreach ($taxBases as $group) {
            $subtotal = Einvoice_pro_decimal::add($subtotal, $group['amount']);
        }
        if (Einvoice_pro_decimal::compare($discount, $subtotal, 2) > 0) {
            throw new Einvoice_pro_validation_exception('perfex.discount', 'The discount exceeds the invoice subtotal.');
        }

        $allowances = [];
        $allocated = '0.00';
        $keys = array_keys($taxBases);
        foreach ($keys as $position => $key) {
            $group = $taxBases[$key];
            $amount = $position === count($keys) - 1
                ? Einvoice_pro_decimal::subtract($discount, $allocated)
                : Einvoice_pro_decimal::multiply(
                    $discount,
                    Einvoice_pro_decimal::divide($group['amount'], $subtotal, 8)
                );
            $allocated = Einvoice_pro_decimal::add($allocated, $amount);
            if (Einvoice_pro_decimal::isZero($amount, 2)) {
                continue;
            }

            $allowances[] = [
                'charge' => false,
                'amount' => $amount,
                'base_amount' => $group['amount'],
                'reason' => 'Discount',
                'tax' => $group['tax'],
            ];
        }

        return $allowances;
    }

    /**
     * Reads accounting-currency VAT supplied by an explicit integration filter or invoice extension.
     *
     * @param object $invoice
     */
    private function accountingVat($invoice): ?string
    {
        if (isset($invoice->einvoice_pro_tax_amount_ron)) {
            return $this->decimal($invoice->einvoice_pro_tax_amount_ron, 'perfex.tax_amount_ron');
        }

        return null;
    }

    /**
     * Returns the selected invoice notes without adding empty values.
     *
     * @return array<int, string>
     */
    private function selectedNotes(): array
    {
        $notes = [];
        foreach (['1', '2', '3'] as $index) {
            $note = einvoice_pro_selected_note($index);
            if ($note !== null) {
                $notes[] = $note;
            }
        }

        return $notes;
    }

    /**
     * Builds bank-transfer instructions only when an IBAN was configured.
     *
     * @return array<string, string|null>|null
     */
    private function payment(): ?array
    {
        $iban = $this->optionalString(get_option('einvoice_pro_payment_iban'));
        if ($iban === null) {
            return null;
        }

        return [
            'means_code' => '42',
            'iban' => $iban,
            'account_name' => null,
        ];
    }

    /**
     * Formats the configured share capital as a legal-form description when present.
     */
    private function legalForm(): ?string
    {
        $capital = $this->optionalString(get_option('einvoice_pro_company_legal_form'));

        return $capital === null ? null : 'Capital social: ' . $capital;
    }

    /**
     * Resolves a Perfex country identifier to an ISO alpha-2 code.
     *
     * @param mixed $identifier
     */
    private function country($identifier, string $rule): string
    {
        if ((!is_int($identifier) && !is_string($identifier)) || (string) $identifier === '') {
            throw new Einvoice_pro_validation_exception($rule, 'A country is missing from the invoice snapshot.');
        }

        $identifier = trim((string) $identifier);
        if (preg_match('/^[A-Za-z]{2}$/D', $identifier)) {
            return Einvoice_pro_codes::country($identifier);
        }

        $country = get_country($identifier);
        $iso2 = is_object($country) && isset($country->iso2)
            ? $country->iso2
            : (is_array($country) && isset($country['iso2']) ? $country['iso2'] : null);
        if (!is_string($iso2)) {
            throw new Einvoice_pro_validation_exception($rule, 'A country could not be resolved by Perfex.');
        }

        return Einvoice_pro_codes::country($iso2);
    }

    /**
     * Uses Perfex 3.4's explicit company country code before the legacy country identifier.
     *
     * @return mixed
     */
    private function supplierCountrySource()
    {
        $countryCode = get_option('invoice_company_country_code');
        if ((is_string($countryCode) || is_int($countryCode)) && trim((string) $countryCode) !== '') {
            return $countryCode;
        }

        return get_option('invoice_company_country');
    }

    /**
     * Reads one object property while keeping absent optional values distinct.
     *
     * @param object $object
     * @param mixed $default
     * @return mixed
     */
    private function value($object, string $property, $default = null)
    {
        return is_object($object) && property_exists($object, $property) ? $object->{$property} : $default;
    }

    /**
     * Accepts a required UTF-8 string from a Perfex snapshot or option.
     *
     * @param mixed $value
     */
    private function string($value, string $rule): string
    {
        $value = $this->optionalString($value);
        if ($value === null) {
            throw new Einvoice_pro_validation_exception($rule, 'A required Perfex invoice value is missing.');
        }

        return $value;
    }

    /**
     * Normalizes an optional scalar string without accepting arrays or objects.
     *
     * @param mixed $value
     */
    private function optionalString($value): ?string
    {
        if ($value === null || $value === false || $value === '') {
            return null;
        }
        if (!is_string($value) && !is_int($value)) {
            throw new Einvoice_pro_validation_exception('perfex.value', 'A Perfex invoice value has an invalid type.');
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (!einvoice_pro_is_valid_utf8($value)) {
            throw new Einvoice_pro_validation_exception('perfex.utf8', 'A Perfex invoice value is not valid UTF-8.');
        }

        return $value;
    }

    /**
     * Treats a fiscal identifier as a VAT identifier only when it carries the country's VAT prefix.
     *
     * Perfex stores both VAT and non-VAT fiscal identifiers in the same customer field. Keeping the
     * unprefixed value as a legal identifier avoids falsely declaring a non-VAT entity as VAT registered.
     */
    private function vatIdentifier(?string $identifier, string $country): ?string
    {
        if ($identifier === null) {
            return null;
        }

        $compact = strtoupper(preg_replace('/[\s.\-]+/', '', $identifier) ?? '');
        $prefix = self::VAT_PREFIXES[$country] ?? null;
        if ($prefix === null) {
            return null;
        }
        if (!preg_match('/^' . preg_quote($prefix, '/') . '[A-Z0-9]{2,18}$/D', $compact)) {
            return null;
        }
        if ($country === 'RO' && !preg_match('/^RO[0-9]{2,10}$/D', $compact)) {
            return null;
        }

        return $compact;
    }

    /**
     * Accepts a decimal database value without converting through float.
     *
     * @param mixed $value
     */
    private function decimal($value, string $rule): string
    {
        if (!is_string($value) && !is_int($value)) {
            throw new Einvoice_pro_validation_exception($rule, 'A Perfex monetary value is not an exact decimal.');
        }

        return (string) $value;
    }

    /**
     * Reads a decimal field from an invoice item.
     *
     * @param array<string, mixed> $item
     */
    private function decimalItem(array $item, string $name): string
    {
        if (!array_key_exists($name, $item)) {
            throw new Einvoice_pro_validation_exception('perfex.line_' . $name, 'A line decimal is missing.');
        }

        return $this->decimal($item[$name], 'perfex.line_' . $name);
    }

    /**
     * Validates a mandatory ISO date read from Perfex.
     *
     * @param mixed $value
     */
    private function date($value, string $rule): string
    {
        $value = $this->string($value, $rule);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value
        ) {
            throw new Einvoice_pro_validation_exception($rule, 'A Perfex invoice date is invalid.');
        }

        return $value;
    }

    /**
     * Validates an optional ISO date without turning a missing date into 1970-01-01.
     *
     * @param mixed $value
     */
    private function optionalDate($value, string $rule): ?string
    {
        $value = $this->optionalString($value);

        return $value === null ? null : $this->date($value, $rule);
    }

    /**
     * Creates a stable key for one VAT profile.
     *
     * @param array<string, mixed> $tax
     */
    private function taxKey(array $tax): string
    {
        return implode('|', [
            $tax['category'], $tax['rate'], (string) $tax['exemption_code'], (string) $tax['exemption_reason'],
        ]);
    }
}
