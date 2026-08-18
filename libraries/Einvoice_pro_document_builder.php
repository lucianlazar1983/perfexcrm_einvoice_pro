<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Validates canonical invoice input and derives all monetary and VAT totals.
 */
final class Einvoice_pro_document_builder
{
    private const B2C_ZERO_IDENTIFIER_FROM = '2026-01-15';

    /**
     * Builds a deterministic document that is ready for UBL serialization.
     *
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    public function build(array $source): array
    {
        $document = [
            'customization_id' => EINVOICE_PRO_CUSTOMIZATION_ID,
            'id' => $this->text($source['id'] ?? null, 'invoice.id', 100),
            'issue_date' => $this->date($source['issue_date'] ?? null, 'invoice.issue_date'),
            'due_date' => $this->optionalDate($source['due_date'] ?? null, 'invoice.due_date'),
            'payment_terms' => $this->optionalText($source['payment_terms'] ?? null, 'invoice.payment_terms', 500),
            'type_code' => $this->invoiceType($source['type_code'] ?? '380'),
            'currency' => Einvoice_pro_codes::currency($this->scalar($source['currency'] ?? null, 'invoice.currency')),
            'notes' => $this->notes($source['notes'] ?? []),
            'seller' => $this->party($source['seller'] ?? null, 'seller'),
            'buyer' => $this->buyer($source['buyer'] ?? null, (string) ($source['issue_date'] ?? '')),
            'payment' => $this->payment($source['payment'] ?? null),
            'lines' => [],
            'allowances_charges' => [],
            'tax_subtotals' => [],
        ];

        $lines = $source['lines'] ?? null;
        if (!is_array($lines) || $lines === []) {
            throw new Einvoice_pro_validation_exception('invoice.lines', 'The invoice must contain at least one line.');
        }

        $lineTotal = '0.00';
        $taxGroups = [];
        foreach (array_values($lines) as $lineIndex => $lineSource) {
            if (!is_array($lineSource)) {
                throw new Einvoice_pro_validation_exception('line.invalid', 'An invoice line has an invalid structure.');
            }

            $line = $this->line($lineSource, $lineIndex + 1);
            $document['lines'][] = $line;
            $lineTotal = Einvoice_pro_decimal::add($lineTotal, $line['net_amount']);
            $this->addTaxableAmount($taxGroups, $line['tax'], $line['net_amount']);
        }

        $allowanceTotal = '0.00';
        $chargeTotal = '0.00';
        $allowancesCharges = $source['allowances_charges'] ?? [];
        if (!is_array($allowancesCharges)) {
            throw new Einvoice_pro_validation_exception('allowance.invalid', 'Invoice allowances and charges are invalid.');
        }

        foreach (array_values($allowancesCharges) as $adjustmentSource) {
            if (!is_array($adjustmentSource)) {
                throw new Einvoice_pro_validation_exception('allowance.invalid', 'An invoice allowance or charge is invalid.');
            }

            $adjustment = $this->allowanceCharge($adjustmentSource);
            $document['allowances_charges'][] = $adjustment;
            if ($adjustment['charge']) {
                $chargeTotal = Einvoice_pro_decimal::add($chargeTotal, $adjustment['amount']);
                $this->addTaxableAmount($taxGroups, $adjustment['tax'], $adjustment['amount']);
            } else {
                $allowanceTotal = Einvoice_pro_decimal::add($allowanceTotal, $adjustment['amount']);
                $this->addTaxableAmount(
                    $taxGroups,
                    $adjustment['tax'],
                    Einvoice_pro_decimal::subtract('0.00', $adjustment['amount'])
                );
            }
        }

        ksort($taxGroups, SORT_STRING);
        $taxTotal = '0.00';
        foreach ($taxGroups as $group) {
            if (Einvoice_pro_decimal::compare($group['taxable_amount'], '0.00', 2) < 0) {
                throw new Einvoice_pro_validation_exception(
                    'tax.negative_base',
                    'An allowance exceeds the taxable amount for its VAT category.'
                );
            }

            $group['tax_amount'] = $group['category'] === 'S'
                ? Einvoice_pro_decimal::percentage($group['taxable_amount'], $group['rate'])
                : '0.00';
            $taxTotal = Einvoice_pro_decimal::add($taxTotal, $group['tax_amount']);
            $document['tax_subtotals'][] = $group;
        }

        $taxExclusive = Einvoice_pro_decimal::add(
            Einvoice_pro_decimal::subtract($lineTotal, $allowanceTotal),
            $chargeTotal
        );
        $taxInclusive = Einvoice_pro_decimal::add($taxExclusive, $taxTotal);
        $prepaid = Einvoice_pro_decimal::normalize($source['prepaid_amount'] ?? '0');
        $rounding = Einvoice_pro_decimal::normalize($source['rounding_amount'] ?? '0');
        $payable = Einvoice_pro_decimal::add(
            Einvoice_pro_decimal::subtract($taxInclusive, $prepaid),
            $rounding
        );

        if (Einvoice_pro_decimal::compare($prepaid, '0.00', 2) < 0) {
            throw new Einvoice_pro_validation_exception('total.prepaid', 'The prepaid amount cannot be negative.');
        }
        if (Einvoice_pro_decimal::compare($payable, '0.00', 2) > 0
            && $document['due_date'] === null
            && $document['payment_terms'] === null
        ) {
            throw new Einvoice_pro_validation_exception(
                'payment.due',
                'A positive payable amount requires a due date or payment terms.'
            );
        }

        $document['totals'] = [
            'line_extension' => $lineTotal,
            'allowance_total' => $allowanceTotal,
            'charge_total' => $chargeTotal,
            'tax_exclusive' => $taxExclusive,
            'tax_amount' => $taxTotal,
            'tax_inclusive' => $taxInclusive,
            'prepaid' => $prepaid,
            'rounding' => $rounding,
            'payable' => $payable,
        ];

        $this->checkExpectedTotals($source['expected_totals'] ?? [], $document['totals']);
        $this->accountingCurrency($source, $document);
        $this->checkTaxPartyRules($document);

        return $document;
    }

    /**
     * Validates and normalizes one invoice line.
     *
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    private function line(array $source, int $position): array
    {
        $quantity = Einvoice_pro_decimal::normalize($source['quantity'] ?? '', 4);
        if (Einvoice_pro_decimal::compare($quantity, '0', 4) <= 0) {
            throw new Einvoice_pro_validation_exception('line.quantity', 'Invoice line quantities must be positive.');
        }

        $price = Einvoice_pro_decimal::normalize($source['price_amount'] ?? '', 4);
        if (Einvoice_pro_decimal::compare($price, '0', 4) < 0) {
            throw new Einvoice_pro_validation_exception('line.price', 'Invoice line prices cannot be negative.');
        }

        $calculatedNet = Einvoice_pro_decimal::multiply($quantity, $price);
        $net = array_key_exists('net_amount', $source)
            ? Einvoice_pro_decimal::normalize($source['net_amount'])
            : $calculatedNet;
        if (Einvoice_pro_decimal::compare($calculatedNet, $net, 2) !== 0) {
            throw new Einvoice_pro_validation_exception(
                'line.reconciliation',
                'An invoice line does not reconcile with its quantity and unit price.'
            );
        }

        return [
            'id' => $this->text($source['id'] ?? (string) $position, 'line.id', 100),
            'quantity' => $quantity,
            'unit_code' => Einvoice_pro_codes::unit($this->scalar($source['unit'] ?? '', 'line.unit')),
            'net_amount' => $net,
            'name' => $this->text($source['name'] ?? null, 'line.name', 200),
            'description' => $this->optionalText($source['description'] ?? null, 'line.description', 1000),
            'price_amount' => $price,
            'tax' => $this->tax($source['tax'] ?? null),
        ];
    }

    /**
     * Validates a document-level allowance or charge.
     *
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    private function allowanceCharge(array $source): array
    {
        if (!isset($source['charge']) || !is_bool($source['charge'])) {
            throw new Einvoice_pro_validation_exception('allowance.indicator', 'An allowance or charge has no valid indicator.');
        }

        $amount = Einvoice_pro_decimal::normalize($source['amount'] ?? '');
        if (Einvoice_pro_decimal::compare($amount, '0.00', 2) <= 0) {
            throw new Einvoice_pro_validation_exception('allowance.amount', 'Allowance and charge amounts must be positive.');
        }

        return [
            'charge' => $source['charge'],
            'amount' => $amount,
            'base_amount' => isset($source['base_amount'])
                ? Einvoice_pro_decimal::normalize($source['base_amount'])
                : null,
            'percentage' => isset($source['percentage'])
                ? Einvoice_pro_decimal::normalize($source['percentage'], 4)
                : null,
            'reason' => $this->text($source['reason'] ?? null, 'allowance.reason', 200),
            'reason_code' => $this->optionalText($source['reason_code'] ?? null, 'allowance.reason_code', 20),
            'tax' => $this->tax($source['tax'] ?? null),
        ];
    }

    /**
     * Normalizes a VAT category together with the rate and exemption information it requires.
     *
     * @param mixed $source
     * @return array<string, string|null>
     */
    private function tax($source): array
    {
        if (!is_array($source)) {
            throw new Einvoice_pro_validation_exception('tax.missing', 'An invoice line has no VAT classification.');
        }

        $category = strtoupper($this->scalar($source['category'] ?? null, 'tax.category'));
        if (!in_array($category, ['S', 'E', 'AE', 'O'], true)) {
            throw new Einvoice_pro_validation_exception('tax.category', 'The VAT category is not supported.');
        }

        $rate = Einvoice_pro_decimal::normalize($source['rate'] ?? '0', 2);
        $reason = $this->optionalText($source['exemption_reason'] ?? null, 'tax.exemption_reason', 500);
        $reasonCode = $this->optionalText($source['exemption_code'] ?? null, 'tax.exemption_code', 50);

        if ($category === 'S') {
            if (Einvoice_pro_decimal::compare($rate, '0.00', 2) <= 0 || $reason !== null || $reasonCode !== null) {
                throw new Einvoice_pro_validation_exception(
                    'tax.standard',
                    'Standard VAT requires a positive rate and no exemption reason.'
                );
            }
        } elseif (!Einvoice_pro_decimal::isZero($rate, 2) || ($reason === null && $reasonCode === null)) {
            throw new Einvoice_pro_validation_exception(
                'tax.exemption',
                'A zero VAT category requires a zero rate and an exemption reason or code.'
            );
        }

        return [
            'category' => $category,
            'rate' => $rate,
            'exemption_reason' => $reason,
            'exemption_code' => $reasonCode,
        ];
    }

    /**
     * Adds an amount to the VAT breakdown identified by category, rate, and exemption.
     *
     * @param array<string, array<string, mixed>> $groups
     * @param array<string, string|null> $tax
     */
    private function addTaxableAmount(array &$groups, array $tax, string $amount): void
    {
        $key = implode('|', [
            $tax['category'],
            $tax['rate'],
            (string) $tax['exemption_code'],
            (string) $tax['exemption_reason'],
        ]);

        if (!isset($groups[$key])) {
            $groups[$key] = $tax + ['taxable_amount' => '0.00', 'tax_amount' => '0.00'];
        }

        $groups[$key]['taxable_amount'] = Einvoice_pro_decimal::add(
            $groups[$key]['taxable_amount'],
            $amount
        );
    }

    /**
     * Validates a seller or buyer party and its invoice address.
     *
     * @param mixed $source
     * @return array<string, mixed>
     */
    private function party($source, string $role): array
    {
        if (!is_array($source)) {
            throw new Einvoice_pro_validation_exception($role . '.missing', 'An invoice party is missing.');
        }

        $addressSource = $source['address'] ?? null;
        if (!is_array($addressSource)) {
            throw new Einvoice_pro_validation_exception($role . '.address', 'An invoice party address is missing.');
        }

        $country = Einvoice_pro_codes::country(
            $this->scalar($addressSource['country_code'] ?? null, $role . '.country')
        );
        $party = [
            'name' => $this->text($source['name'] ?? null, $role . '.name', 200),
            'legal_id' => $this->optionalText($source['legal_id'] ?? null, $role . '.legal_id', 100),
            'vat_id' => $this->optionalText($source['vat_id'] ?? null, $role . '.vat_id', 50),
            'endpoint' => $this->optionalText($source['endpoint'] ?? null, $role . '.endpoint', 200),
            'endpoint_scheme' => $this->optionalText($source['endpoint_scheme'] ?? null, $role . '.endpoint_scheme', 20),
            'legal_form' => $this->optionalText($source['legal_form'] ?? null, $role . '.legal_form', 200),
            'phone' => $this->optionalText($source['phone'] ?? null, $role . '.phone', 50),
            'email' => $this->optionalEmail($source['email'] ?? null, $role . '.email'),
            'address' => [
                'street' => $this->text($addressSource['street'] ?? null, $role . '.street', 300),
                'city' => $this->text($addressSource['city'] ?? null, $role . '.city', 100),
                'subdivision' => Einvoice_pro_codes::subdivision(
                    $country,
                    $this->optionalScalar($addressSource['subdivision'] ?? null, $role . '.subdivision') ?? ''
                ),
                'country_code' => $country,
            ],
        ];

        if ($party['legal_id'] === null && $party['vat_id'] === null) {
            throw new Einvoice_pro_validation_exception($role . '.identifier', 'An invoice party has no fiscal identifier.');
        }

        if (($party['endpoint'] === null) !== ($party['endpoint_scheme'] === null)) {
            throw new Einvoice_pro_validation_exception($role . '.endpoint', 'An electronic address requires its scheme.');
        }

        return $party;
    }

    /**
     * Applies the explicit B2B or B2C identity decision to the buyer.
     *
     * @param mixed $source
     * @return array<string, mixed>
     */
    private function buyer($source, string $issueDate): array
    {
        if (!is_array($source)) {
            throw new Einvoice_pro_validation_exception('buyer.missing', 'The buyer is missing.');
        }

        $type = $this->scalar($source['type'] ?? 'business', 'buyer.type');
        if (!in_array($type, ['business', 'individual_identified', 'individual_unidentified'], true)) {
            throw new Einvoice_pro_validation_exception('buyer.type', 'The buyer type is not supported.');
        }

        if ($type === 'individual_unidentified') {
            $address = $source['address'] ?? null;
            $country = is_array($address) ? strtoupper((string) ($address['country_code'] ?? '')) : '';
            if ($country !== 'RO' || $issueDate < self::B2C_ZERO_IDENTIFIER_FROM) {
                throw new Einvoice_pro_validation_exception(
                    'buyer.unidentified_date',
                    'The unidentified B2C identifier is not applicable to this invoice.'
                );
            }
            $source['legal_id'] = '0000000000000';
            $source['vat_id'] = null;
        }

        $buyer = $this->party($source, 'buyer');
        $buyer['type'] = $type;

        return $buyer;
    }

    /**
     * Validates optional payment instructions.
     *
     * @param mixed $source
     * @return array<string, string|null>|null
     */
    private function payment($source): ?array
    {
        if ($source === null || $source === []) {
            return null;
        }
        if (!is_array($source)) {
            throw new Einvoice_pro_validation_exception('payment.invalid', 'Payment instructions are invalid.');
        }

        $code = $this->scalar($source['means_code'] ?? null, 'payment.means_code');
        if (!preg_match('/^[0-9]{1,3}$/D', $code)) {
            throw new Einvoice_pro_validation_exception('payment.means_code', 'The payment means code is invalid.');
        }

        $iban = $this->optionalScalar($source['iban'] ?? null, 'payment.iban');

        return [
            'means_code' => $code,
            'iban' => $iban === null ? null : Einvoice_pro_codes::iban($iban),
            'account_name' => $this->optionalText($source['account_name'] ?? null, 'payment.account_name', 200),
        ];
    }

    /**
     * Adds the optional accounting-currency VAT total only when the currencies differ.
     *
     * @param array<string, mixed> $source
     * @param array<string, mixed> $document
     */
    private function accountingCurrency(array $source, array &$document): void
    {
        $taxCurrencySource = $source['tax_currency'] ?? null;
        if ($taxCurrencySource === null || $taxCurrencySource === '') {
            if ($document['seller']['address']['country_code'] === 'RO' && $document['currency'] !== 'RON') {
                throw new Einvoice_pro_validation_exception(
                    'currency.accounting_required',
                    'A Romanian foreign-currency invoice requires its RON VAT accounting total.'
                );
            }
            $document['tax_currency'] = null;
            $document['tax_amount_accounting'] = null;
            return;
        }

        $taxCurrency = Einvoice_pro_codes::currency($this->scalar($taxCurrencySource, 'invoice.tax_currency'));
        if ($taxCurrency === $document['currency']) {
            throw new Einvoice_pro_validation_exception(
                'currency.tax_same',
                'The VAT accounting currency must differ from the invoice currency.'
            );
        }
        if (!array_key_exists('tax_amount_accounting', $source)) {
            throw new Einvoice_pro_validation_exception(
                'currency.tax_total',
                'The VAT accounting currency requires its VAT total.'
            );
        }

        $document['tax_currency'] = $taxCurrency;
        $document['tax_amount_accounting'] = Einvoice_pro_decimal::normalize($source['tax_amount_accounting']);
    }

    /**
     * Compares the derived totals with the snapshot values supplied by Perfex.
     *
     * @param mixed $expected
     * @param array<string, string> $actual
     */
    private function checkExpectedTotals($expected, array $actual): void
    {
        if ($expected === null || $expected === []) {
            return;
        }
        if (!is_array($expected)) {
            throw new Einvoice_pro_validation_exception('total.invalid', 'The expected totals are invalid.');
        }

        foreach ($expected as $name => $value) {
            if (!is_string($name) || !array_key_exists($name, $actual)) {
                throw new Einvoice_pro_validation_exception('total.unknown', 'An expected total is not recognized.');
            }
            if (Einvoice_pro_decimal::compare($value, $actual[$name], 2) !== 0) {
                throw new Einvoice_pro_validation_exception(
                    'total.' . $name,
                    'The generated totals do not reconcile with the Perfex invoice snapshot.'
                );
            }
        }
    }

    /**
     * Enforces party-identifier rules tied to the VAT categories in the document.
     *
     * @param array<string, mixed> $document
     */
    private function checkTaxPartyRules(array $document): void
    {
        foreach ($document['tax_subtotals'] as $subtotal) {
            if ($subtotal['category'] === 'O'
                && ($document['seller']['vat_id'] !== null || $document['buyer']['vat_id'] !== null)
            ) {
                throw new Einvoice_pro_validation_exception(
                    'tax.outside_scope_identifiers',
                    'Outside-scope VAT invoices cannot contain seller or buyer VAT identifiers.'
                );
            }
            if ($subtotal['category'] === 'S' && $document['seller']['vat_id'] === null) {
                throw new Einvoice_pro_validation_exception(
                    'tax.standard_seller',
                    'Standard VAT invoices require the seller VAT identifier.'
                );
            }
            if ($subtotal['category'] === 'AE'
                && ($document['seller']['vat_id'] === null || $document['buyer']['vat_id'] === null)
            ) {
                throw new Einvoice_pro_validation_exception(
                    'tax.reverse_charge_identifiers',
                    'Reverse-charge invoices require seller and buyer VAT identifiers.'
                );
            }
        }
    }

    /**
     * Validates the supported invoice type codes.
     *
     * @param mixed $value
     */
    private function invoiceType($value): string
    {
        $value = $this->scalar($value, 'invoice.type_code');
        if (!in_array($value, ['380', '384', '389', '751'], true)) {
            throw new Einvoice_pro_validation_exception('invoice.type_code', 'The invoice type is not supported.');
        }

        return $value;
    }

    /**
     * Normalizes the optional invoice notes while keeping their original order.
     *
     * @param mixed $notes
     * @return array<int, string>
     */
    private function notes($notes): array
    {
        if (!is_array($notes) || count($notes) > 20) {
            throw new Einvoice_pro_validation_exception('invoice.notes', 'The invoice notes are invalid.');
        }

        $result = [];
        foreach ($notes as $note) {
            $result[] = $this->preservedText($note, 'invoice.note', 1000);
        }

        return $result;
    }

    /**
     * Validates a mandatory text value and rejects XML-prohibited characters.
     *
     * @param mixed $value
     */
    private function text($value, string $rule, int $maximumLength): string
    {
        $value = trim($this->scalar($value, $rule));
        if ($value === '' || mb_strlen($value, 'UTF-8') > $maximumLength || !$this->validXmlText($value)) {
            throw new Einvoice_pro_validation_exception($rule, 'A required invoice value is missing or invalid.');
        }

        return $value;
    }

    /**
     * Validates note text without changing administrator-defined whitespace or line breaks.
     *
     * @param mixed $value
     */
    private function preservedText($value, string $rule, int $maximumLength): string
    {
        $value = $this->scalar($value, $rule);
        if (trim($value) === '' || mb_strlen($value, 'UTF-8') > $maximumLength || !$this->validXmlText($value)) {
            throw new Einvoice_pro_validation_exception($rule, 'An invoice note is missing or invalid.');
        }

        return $value;
    }

    /**
     * Validates optional text without converting an absent value to an empty XML element.
     *
     * @param mixed $value
     */
    private function optionalText($value, string $rule, int $maximumLength): ?string
    {
        $value = $this->optionalScalar($value, $rule);
        if ($value === null) {
            return null;
        }

        return $this->text($value, $rule, $maximumLength);
    }

    /**
     * Validates an optional email address used only as contact information.
     *
     * @param mixed $value
     */
    private function optionalEmail($value, string $rule): ?string
    {
        $value = $this->optionalText($value, $rule, 254);
        if ($value !== null && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new Einvoice_pro_validation_exception($rule, 'An invoice contact email is invalid.');
        }

        return $value;
    }

    /**
     * Accepts a mandatory scalar without silently converting arrays or objects.
     *
     * @param mixed $value
     */
    private function scalar($value, string $rule): string
    {
        if (!is_string($value) && !is_int($value)) {
            throw new Einvoice_pro_validation_exception($rule, 'An invoice value has an invalid type.');
        }

        $value = (string) $value;
        if (!einvoice_pro_is_valid_utf8($value)) {
            throw new Einvoice_pro_validation_exception($rule, 'An invoice value is not valid UTF-8.');
        }

        return $value;
    }

    /**
     * Accepts an optional scalar and treats blank text as absent.
     *
     * @param mixed $value
     */
    private function optionalScalar($value, string $rule): ?string
    {
        if ($value === null || $value === false || $value === '') {
            return null;
        }

        $value = trim($this->scalar($value, $rule));

        return $value === '' ? null : $value;
    }

    /**
     * Validates a mandatory ISO calendar date without timezone conversion.
     *
     * @param mixed $value
     */
    private function date($value, string $rule): string
    {
        $value = $this->scalar($value, $rule);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value
        ) {
            throw new Einvoice_pro_validation_exception($rule, 'An invoice date is invalid.');
        }

        return $value;
    }

    /**
     * Validates an optional ISO calendar date.
     *
     * @param mixed $value
     */
    private function optionalDate($value, string $rule): ?string
    {
        $value = $this->optionalScalar($value, $rule);

        return $value === null ? null : $this->date($value, $rule);
    }

    /**
     * Rejects characters that cannot appear in XML 1.0 text nodes.
     */
    private function validXmlText(string $value): bool
    {
        return preg_match(
            '/^[\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]*$/u',
            $value
        ) === 1;
    }
}
