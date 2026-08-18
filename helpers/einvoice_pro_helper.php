<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once dirname(__DIR__) . '/libraries/Einvoice_pro_validation_exception.php';
require_once dirname(__DIR__) . '/libraries/Einvoice_pro_decimal.php';
require_once dirname(__DIR__) . '/libraries/Einvoice_pro_codes.php';
require_once dirname(__DIR__) . '/libraries/Einvoice_pro_document_builder.php';
require_once dirname(__DIR__) . '/libraries/Einvoice_pro_perfex_mapper.php';
require_once dirname(__DIR__) . '/libraries/Einvoice_pro_ubl_serializer.php';

/**
 * Accepts only canonical positive integer route values.
 *
 * @param mixed $value Value received from the router.
 */
function einvoice_pro_positive_integer($value): ?int
{
    if (is_int($value)) {
        return $value > 0 ? $value : null;
    }

    if (!is_string($value) || !preg_match('/^[1-9][0-9]*$/D', $value)) {
        return null;
    }

    $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    return $integer === false ? null : $integer;
}

/**
 * Applies the invoice view and view-own capabilities to one concrete invoice.
 *
 * The ownership check is intentionally conservative: it accepts only the creator or assigned
 * sales agent when the staff member has Perfex's view-own capability.
 *
 * @param mixed $invoice Invoice object supplied by Perfex.
 */
function einvoice_pro_can_view_invoice($invoice): bool
{
    if (!is_object($invoice) || !is_staff_logged_in()) {
        return false;
    }

    if (staff_can('view', 'invoices')) {
        return true;
    }

    if (!staff_can('view_own', 'invoices')) {
        return false;
    }

    $staffId = (int) get_staff_user_id();
    $creatorId = isset($invoice->addedfrom) ? (int) $invoice->addedfrom : 0;
    $salesAgentId = isset($invoice->sale_agent) ? (int) $invoice->sale_agent : 0;

    return $staffId > 0 && ($creatorId === $staffId || $salesAgentId === $staffId);
}

/**
 * Produces an ASCII filename that cannot add headers or escape the download directory.
 */
function einvoice_pro_xml_filename(string $invoiceNumber): string
{
    $name = preg_replace('/[\\x00-\\x1F\\x7F\\\\\/\"]+/u', '-', trim($invoiceNumber));
    $name = preg_replace('/[^A-Za-z0-9._-]+/u', '-', (string) $name);
    $name = trim((string) $name, '.-_');

    if ($name === '') {
        $name = 'invoice';
    }

    return substr($name, 0, 120) . '.xml';
}

/**
 * Checks UTF-8 input without depending on PHP's default encoding.
 */
function einvoice_pro_is_valid_utf8(string $value): bool
{
    if (function_exists('mb_check_encoding')) {
        return mb_check_encoding($value, 'UTF-8');
    }

    return preg_match('//u', $value) === 1;
}

/**
 * Decodes legacy custom-note JSON without modifying the stored value on error.
 *
 * @return array{valid: bool, notes: array<int, string>}
 */
function einvoice_pro_decode_custom_notes($storedValue): array
{
    if ($storedValue === '' || $storedValue === null || $storedValue === false) {
        return ['valid' => true, 'notes' => []];
    }

    $notes = json_decode((string) $storedValue, true);
    if (!is_array($notes) || json_last_error() !== JSON_ERROR_NONE) {
        return ['valid' => false, 'notes' => []];
    }

    foreach ($notes as $note) {
        if (
            !is_string($note)
            || !einvoice_pro_is_valid_utf8($note)
            || mb_strlen($note, 'UTF-8') > EINVOICE_PRO_MAX_NOTE_LENGTH
        ) {
            return ['valid' => false, 'notes' => []];
        }
    }

    return ['valid' => true, 'notes' => array_values($notes)];
}

/**
 * Prepares settings values and note choices so the view performs no data access.
 *
 * @return array<string, mixed>
 */
function einvoice_pro_settings_data(): array
{
    $noteDefaults = [
        '1' => [
            ['id' => '', 'name' => _l('e_invoice_option_none')],
            ['id' => 'TVA la incasare', 'name' => _l('e_invoice_option_tva')],
        ],
        '2' => [
            ['id' => '', 'name' => _l('e_invoice_option_none')],
            [
                'id'   => 'Factura este valabila fara semnatura si stampila, conform art. 319 alin. 29 din legea 227/2015',
                'name' => _l('e_invoice_option_validity'),
            ],
        ],
        '3' => [
            ['id' => '', 'name' => _l('e_invoice_option_none')],
            ['id' => 'Modalitate plata -OP Bancar', 'name' => _l('e_invoice_option_payment')],
        ],
    ];
    $labels = [
        '1' => _l('e_invoice_note1'),
        '2' => _l('e_invoice_note2'),
        '3' => _l('e_invoice_note3'),
    ];
    $sections = [];

    foreach (['1', '2', '3'] as $index) {
        $decoded = einvoice_pro_decode_custom_notes(get_option('einvoice_pro_custom_notes_' . $index));
        $options = $noteDefaults[$index];

        foreach ($decoded['notes'] as $note) {
            $options[] = ['id' => $note, 'name' => $note];
        }

        $sections[] = [
            'index'         => $index,
            'label'         => $labels[$index],
            'selected'      => get_option('einvoice_pro_note_' . $index),
            'options'       => $options,
            'custom_notes'  => $decoded['notes'],
            'storage_valid' => $decoded['valid'],
        ];
    }

    return [
        'languages' => [
            ['id' => 'romanian', 'name' => _l('e_invoice_language_romanian')],
            ['id' => 'english', 'name' => _l('e_invoice_language_english')],
        ],
        'xml_language'       => get_option('einvoice_pro_xml_language'),
        'default_unit_code'  => get_option('einvoice_pro_default_unit_code'),
        'unit_codes' => [
            ['id' => 'H87', 'name' => _l('e_invoice_unit_piece')],
            ['id' => 'E48', 'name' => _l('e_invoice_unit_service')],
            ['id' => 'HUR', 'name' => _l('e_invoice_unit_hour')],
            ['id' => 'DAY', 'name' => _l('e_invoice_unit_day')],
            ['id' => 'MON', 'name' => _l('e_invoice_unit_month')],
            ['id' => 'KGM', 'name' => _l('e_invoice_unit_kilogram')],
            ['id' => 'MTR', 'name' => _l('e_invoice_unit_metre')],
            ['id' => 'MTK', 'name' => _l('e_invoice_unit_square_metre')],
            ['id' => 'MTQ', 'name' => _l('e_invoice_unit_cubic_metre')],
            ['id' => 'LTR', 'name' => _l('e_invoice_unit_litre')],
        ],
        'registration'       => get_option('einvoice_pro_registration_number'),
        'company_legal_form' => get_option('einvoice_pro_company_legal_form'),
        'payment_iban'       => get_option('einvoice_pro_payment_iban'),
        'payment_bank_name'  => get_option('einvoice_pro_payment_bank_name'),
        'note_sections'      => $sections,
    ];
}

/**
 * Resolves one selected note, translating only the released preset values.
 *
 * Administrator-defined values are returned exactly as stored and encoded later by the DOM serializer.
 */
function einvoice_pro_selected_note(string $index): ?string
{
    $selected = get_option('einvoice_pro_note_' . $index);
    if (!is_string($selected) || $selected === '') {
        return null;
    }

    $presets = [
        '1' => [
            'value' => 'TVA la incasare',
            'key'   => 'e_invoice_option_tva',
        ],
        '2' => [
            'value' => 'Factura este valabila fara semnatura si stampila, conform art. 319 alin. 29 din legea 227/2015',
            'key'   => 'e_invoice_option_validity',
        ],
        '3' => [
            'value' => 'Modalitate plata -OP Bancar',
            'key'   => 'e_invoice_option_payment',
        ],
    ];

    if (isset($presets[$index]) && hash_equals($presets[$index]['value'], $selected)) {
        return _l($presets[$index]['key']);
    }

    return $selected;
}

/**
 * Maps, reconciles, and serializes one Perfex invoice through the 2.0.2 pipeline.
 *
 * @param object $invoice Perfex invoice snapshot with items and customer data.
 */
function einvoice_pro_generate_xml($invoice): string
{
    $mapper = new Einvoice_pro_perfex_mapper();
    $builder = new Einvoice_pro_document_builder();
    $serializer = new Einvoice_pro_ubl_serializer();

    return $serializer->serialize($builder->build($mapper->map($invoice)));
}

/**
 * Converts a validation rule into a localized, non-sensitive explanation.
 */
function einvoice_pro_validation_message(string $rule): string
{
    $prefix = strstr($rule, '.', true);
    $languageKeys = [
        'invoice' => 'e_invoice_error_invoice',
        'seller' => 'e_invoice_error_identity',
        'buyer' => 'e_invoice_error_identity',
        'country' => 'e_invoice_error_identity',
        'subdivision' => 'e_invoice_error_identity',
        'line' => 'e_invoice_error_line',
        'unit' => 'e_invoice_error_line',
        'tax' => 'e_invoice_error_tax',
        'allowance' => 'e_invoice_error_total',
        'total' => 'e_invoice_error_total',
        'currency' => 'e_invoice_error_currency',
        'payment' => 'e_invoice_error_payment',
        'perfex' => 'e_invoice_error_source',
        'decimal' => 'e_invoice_error_source',
    ];

    return _l($languageKeys[$prefix] ?? 'e_invoice_error_generic');
}

/**
 * Validates the complete module settings submission before any option is changed.
 *
 * @param mixed $payload
 * @return array{valid: bool, values: array<string, string>}
 */
function einvoice_pro_validate_settings($payload): array
{
    if (!is_array($payload)) {
        return ['valid' => false, 'values' => []];
    }

    $language = $payload['einvoice_pro_xml_language'] ?? null;
    $unit = $payload['einvoice_pro_default_unit_code'] ?? null;
    if (!is_string($language) || !in_array($language, ['english', 'romanian'], true) || !is_string($unit)) {
        return ['valid' => false, 'values' => []];
    }

    try {
        $unit = Einvoice_pro_codes::unit($unit);
        $registration = einvoice_pro_setting_text(
            $payload['einvoice_pro_registration_number'] ?? '',
            100,
            true
        );
        $capital = einvoice_pro_setting_text(
            $payload['einvoice_pro_company_legal_form'] ?? '',
            30,
            true
        );
        $iban = einvoice_pro_setting_text($payload['einvoice_pro_payment_iban'] ?? '', 34, true);
        $bank = einvoice_pro_setting_text($payload['einvoice_pro_payment_bank_name'] ?? '', 200, true);
    } catch (Einvoice_pro_validation_exception $exception) {
        return ['valid' => false, 'values' => []];
    }

    if ($capital !== '' && !preg_match('/^[0-9]+(?:\.[0-9]{1,2})?$/D', $capital)) {
        return ['valid' => false, 'values' => []];
    }
    if ($iban !== '') {
        try {
            $iban = Einvoice_pro_codes::iban($iban);
        } catch (Einvoice_pro_validation_exception $exception) {
            return ['valid' => false, 'values' => []];
        }
    }

    $values = [
        'einvoice_pro_xml_language' => $language,
        'einvoice_pro_default_unit_code' => $unit,
        'einvoice_pro_registration_number' => $registration,
        'einvoice_pro_company_legal_form' => $capital,
        'einvoice_pro_payment_iban' => $iban,
        'einvoice_pro_payment_bank_name' => $bank,
    ];

    $presets = [
        '1' => ['', 'TVA la incasare'],
        '2' => ['', 'Factura este valabila fara semnatura si stampila, conform art. 319 alin. 29 din legea 227/2015'],
        '3' => ['', 'Modalitate plata -OP Bancar'],
    ];
    foreach (['1', '2', '3'] as $index) {
        $selected = $payload['einvoice_pro_note_' . $index] ?? null;
        if (!is_string($selected)) {
            return ['valid' => false, 'values' => []];
        }

        $decoded = einvoice_pro_decode_custom_notes(get_option('einvoice_pro_custom_notes_' . $index));
        if (!$decoded['valid'] || !in_array($selected, array_merge($presets[$index], $decoded['notes']), true)) {
            return ['valid' => false, 'values' => []];
        }
        $values['einvoice_pro_note_' . $index] = $selected;
    }

    return ['valid' => true, 'values' => $values];
}

/**
 * Normalizes one settings text value while rejecting invalid UTF-8 and control characters.
 *
 * @param mixed $value
 */
function einvoice_pro_setting_text($value, int $maximumLength, bool $allowEmpty): string
{
    if (!is_string($value) || !einvoice_pro_is_valid_utf8($value)) {
        throw new Einvoice_pro_validation_exception('settings.text', 'A settings value is invalid.');
    }

    $value = trim($value);
    if ((!$allowEmpty && $value === '') || mb_strlen($value, 'UTF-8') > $maximumLength) {
        throw new Einvoice_pro_validation_exception('settings.text', 'A settings value is invalid.');
    }
    if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value)) {
        throw new Einvoice_pro_validation_exception('settings.text', 'A settings value is invalid.');
    }

    return $value;
}
