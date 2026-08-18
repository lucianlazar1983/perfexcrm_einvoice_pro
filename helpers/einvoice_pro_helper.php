<?php
defined('BASEPATH') or exit('No direct script access allowed');

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
 * Administrator-defined values are returned exactly as stored and escaped later by the XML view.
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
 * Escapes a scalar for the legacy XML template at its final output boundary.
 *
 * @param mixed $value Value rendered as XML text or an XML attribute.
 */
function einvoice_pro_xml_escape($value): string
{
    $value = $value === null ? '' : (string) $value;

    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Builds the data consumed by the legacy UBL template.
 *
 * This compatibility path is retained for the 1.4.3 upgrade. It will be replaced by the
 * validated canonical mapper and DOM serializer described in ARCHITECTURE.md.
 *
 * @param object $invoice Perfex invoice snapshot with its items and client.
 *
 * @return array<string, mixed>
 */
function einvoice_pro_generate_template_data($invoice): array
{
    $CI = &get_instance();
    $CI->load->model('currencies_model');

    // The legacy mapper still reads Perfex options directly until the canonical adapter replaces it.
    $company_vat = get_option('company_vat');
    $company_country = get_country(get_option('invoice_company_country'));
    $company_state_raw = einvoice_pro_xml_escape(get_option('company_state'));
    $company_state = (strpos($company_state_raw, 'RO-') === 0) ? $company_state_raw : 'RO-' . $company_state_raw;
    
    $supplier = [
        'COMPANY_EMAIL'           => einvoice_pro_xml_escape(get_option('smtp_email')),
        'COMPANY_ID_NUMBER'       => einvoice_pro_xml_escape(preg_replace('/[^0-9]/', '', $company_vat)),
        'COMPANY_NAME'            => einvoice_pro_xml_escape(get_option('invoice_company_name')),
        'COMPANY_ADDRESS'         => einvoice_pro_xml_escape(get_option('invoice_company_address')),
        'COMPANY_CITY'            => einvoice_pro_xml_escape(get_option('invoice_company_city')),
        'COMPANY_STATE'           => $company_state,
        'COMPANY_COUNTRY_ISO2'    => $company_country ? einvoice_pro_xml_escape($company_country->iso2) : 'RO',
        'COMPANY_VAT_NUMBER'      => einvoice_pro_xml_escape($company_vat),
        'COMPANY_REG_NUMBER'      => einvoice_pro_xml_escape(get_option('einvoice_pro_registration_number')),
        'COMPANY_LEGAL_FORM'      => 'Capital social: ' . einvoice_pro_xml_escape(get_option('einvoice_pro_company_legal_form')),
        'COMPANY_CONTACT_NAME'    => '',
        'COMPANY_CONTACT_PHONE'   => einvoice_pro_xml_escape(get_option('invoice_company_phonenumber')),
        'PAYMENT_IBAN'            => einvoice_pro_xml_escape(get_option('einvoice_pro_payment_iban')),
        'PAYMENT_BANK_NAME'       => einvoice_pro_xml_escape(get_option('einvoice_pro_payment_bank_name')),
        'PAYMENT_MEANS_CODE'      => '42',
    ];

    $client_country = get_country($invoice->client->country);
    $client_state_raw = einvoice_pro_xml_escape($invoice->billing_state);
    $client_state = (strpos($client_state_raw, 'RO-') === 0) ? $client_state_raw : 'RO-' . $client_state_raw;

    $customer = [
        'CUSTOMER_ID'                  => einvoice_pro_xml_escape($invoice->client->vat),
        'CUSTOMER_NAME'                => einvoice_pro_xml_escape($invoice->client->company),
        'INVOICE_BILLING_ADRESS'       => einvoice_pro_xml_escape($invoice->billing_street),
        'INVOICE_BILLING_CITY'         => einvoice_pro_xml_escape($invoice->billing_city),
        'INVOICE_BILLING_STATE'        => $client_state,
        'INVOICE_BILLING_COUNTRY_ISO2' => $client_country ? einvoice_pro_xml_escape($client_country->iso2) : 'RO',
        'CUSTOMER_VAT_NUMBER'          => einvoice_pro_xml_escape($invoice->client->vat),
    ];

    $currency = $CI->currencies_model->get($invoice->currency);

    $invoice_notes = [];
    foreach (['1', '2', '3'] as $noteIndex) {
        $note = einvoice_pro_selected_note($noteIndex);
        if ($note !== null) {
            $invoice_notes[] = ['NOTE' => einvoice_pro_xml_escape($note)];
        }
    }

    $invoice_details = [
        'INVOICE_ID'         => einvoice_pro_xml_escape(format_invoice_number($invoice->id)),
        'INVOICE_DATE'       => date('Y-m-d', strtotime($invoice->date)),
        'INVOICE_DUE_DATE'   => date('Y-m-d', strtotime($invoice->duedate)),
        'CURRENCY_CODE'      => $currency ? einvoice_pro_xml_escape($currency->name) : 'RON',
        'TAX_CURRENCY_CODE'  => $currency ? einvoice_pro_xml_escape($currency->name) : 'RON',
        'INVOICE_NOTES'      => $invoice_notes,
        'INVOICE_SUBTOTAL'   => number_format($invoice->subtotal, 2, '.', ''),
        'INVOICE_TOTAL_TAX'  => number_format($invoice->total_tax, 2, '.', ''),
        'INVOICE_TOTAL'      => number_format($invoice->total, 2, '.', ''),
        'INVOICE_BALANCE_DUE'=> number_format($invoice->total, 2, '.', ''),
    ];
    
    $line_items = [];
    $tax_subtotals = [];
    foreach ($invoice->items as $item) {
        $item_taxes = get_invoice_item_taxes($item['id']);
        $tax_rate = 0;
        if (!empty($item_taxes)) {
            $tax_rate = $item_taxes[0]['taxrate'];
        }
        $line_items[] = [
            'LINE_ITEM_ORDER'           => $item['item_order'],
            'LINE_ITEM_QUANTITY_NUMBER' => number_format($item['qty'], 3, '.', ''),
            'LINE_ITEM_QUANTITY_UNIT'   => empty($item['unit']) ? 'H87' : einvoice_pro_xml_escape($item['unit']),
            'LINE_ITEM_TOTAL'           => number_format($item['qty'] * $item['rate'], 2, '.', ''),
            'LINE_ITEM_DESCRIPTION'     => einvoice_pro_xml_escape($item['long_description']),
            'LINE_ITEM_NAME'            => einvoice_pro_xml_escape($item['description']),
            'TAX_RATE'                  => number_format($tax_rate, 2, '.', ''),
            'LINE_ITEM_UNIT_PRICE'      => number_format($item['rate'], 4, '.', ''),
        ];
        foreach ($item_taxes as $tax) {
            $current_tax_rate = (float)$tax['taxrate'];
            if (!isset($tax_subtotals[$current_tax_rate])) {
                $tax_subtotals[$current_tax_rate] = [
                    'TAXABLE_AMOUNT' => 0,
                    'TAX_AMOUNT'     => 0,
                    'TAX_RATE'       => number_format($current_tax_rate, 2, '.', ''),
                ];
            }
            $item_base_amount = $item['rate'] * $item['qty'];
            $tax_subtotals[$current_tax_rate]['TAXABLE_AMOUNT'] += $item_base_amount;
            $tax_subtotals[$current_tax_rate]['TAX_AMOUNT'] += ($item_base_amount / 100) * $current_tax_rate;
        }
    }
    
    $final_subtotals = [];
    foreach($tax_subtotals as $subtotal) {
        $final_subtotals[] = [
            'TAXABLE_AMOUNT' => number_format($subtotal['TAXABLE_AMOUNT'], 2, '.', ''),
            'TAX_AMOUNT'     => number_format($subtotal['TAX_AMOUNT'], 2, '.', ''),
            'TAX_RATE'       => $subtotal['TAX_RATE'],
        ];
    }

    return array_merge($supplier, $customer, $invoice_details, ['LINE_ITEMS' => $line_items, 'TAX_SUBTOTALS' => $final_subtotals]);
}
