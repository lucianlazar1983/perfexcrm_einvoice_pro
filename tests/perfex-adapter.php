<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('BASEPATH', __DIR__);
define('EINVOICE_PRO_CUSTOMIZATION_ID', 'urn:cen.eu:en16931:2017#compliant#urn:efactura.mfinante.ro:CIUS-RO:1.0.1');

$adapterOptions = [
    'invoice_company_country_code' => 'RO',
    'invoice_company_country' => '999',
    'company_vat' => 'RO10000001',
    'invoice_company_name' => 'Furnizor Sintetic SRL',
    'invoice_company_address' => 'Strada Exemplu 1',
    'invoice_company_city' => 'București',
    'company_state' => 'B',
    'einvoice_pro_registration_number' => 'J00/1/2026',
    'einvoice_pro_company_legal_form' => '200',
    'invoice_company_phonenumber' => '',
    'einvoice_pro_payment_iban' => 'RO49AAAA1B31007593840000',
    'einvoice_pro_payment_bank_name' => 'Bancă sintetică',
    'einvoice_pro_default_unit_code' => 'H87',
    'einvoice_pro_note_1' => '',
    'einvoice_pro_note_2' => '',
    'einvoice_pro_note_3' => '',
];
$adapterTaxes = [
    '10' => [['taxrate' => '21.00']],
    '11' => [['taxrate' => '11.00']],
];

/**
 * Supplies the UTF-8 check used by the adapter and builder.
 */
function einvoice_pro_is_valid_utf8(string $value): bool
{
    return mb_check_encoding($value, 'UTF-8');
}

/**
 * Reads one synthetic Perfex option.
 *
 * @return mixed
 */
function get_option(string $name)
{
    global $adapterOptions;

    return $adapterOptions[$name] ?? false;
}

/**
 * Resolves the two synthetic country identifiers used by the fixture.
 *
 * @param mixed $identifier
 * @return object|array<string, string>|null
 */
function get_country($identifier)
{
    $countries = [
        '1' => (object) ['iso2' => 'RO'],
        '2' => (object) ['iso2' => 'DE'],
        '3' => ['iso2' => 'RO'],
    ];
    $key = (string) $identifier;

    return $countries[$key] ?? null;
}

/**
 * Returns the synthetic tax records for one item.
 *
 * @param mixed $itemId
 * @return array<int, array<string, string>>
 */
function get_invoice_item_taxes($itemId): array
{
    global $adapterTaxes;

    return $adapterTaxes[(string) $itemId] ?? [];
}

/**
 * Produces a stable invoice number for adapter testing.
 *
 * @param mixed $invoiceId
 */
function format_invoice_number($invoiceId): string
{
    return 'TEST-' . (string) $invoiceId;
}

/**
 * Returns no selected notes for this adapter fixture.
 */
function einvoice_pro_selected_note(string $index): ?string
{
    return null;
}

/**
 * Mimics the small part of Perfex's model loader used by the mapper.
 */
final class AdapterLoader
{
    /**
     * Accepts the currency-model load request.
     */
    public function model(string $name): void
    {
    }
}

/**
 * Mimics the Perfex currency model for a RON invoice.
 */
final class AdapterCurrencies
{
    /**
     * Resolves the only synthetic currency identifier.
     *
     * @param mixed $identifier
     */
    public function get($identifier): ?object
    {
        return (string) $identifier === '1' ? (object) ['name' => 'RON'] : null;
    }
}

/**
 * Provides the services read through get_instance().
 */
final class AdapterCodeIgniter
{
    public AdapterLoader $load;
    public AdapterCurrencies $currencies_model;

    /**
     * Creates the two services required by the adapter.
     */
    public function __construct()
    {
        $this->load = new AdapterLoader();
        $this->currencies_model = new AdapterCurrencies();
    }
}

/**
 * Returns the isolated CodeIgniter service container by reference.
 */
function &get_instance(): AdapterCodeIgniter
{
    static $instance;

    if (!$instance) {
        $instance = new AdapterCodeIgniter();
    }

    return $instance;
}

/**
 * Passes extension filters through unchanged in the adapter fixture.
 */
final class AdapterHooks
{
    /**
     * Returns the document source supplied by the mapper.
     *
     * @param mixed $value
     * @param array<string, mixed> $additionalParams
     * @return mixed
     */
    public function apply_filters(string $tag, $value, array $additionalParams = [])
    {
        return $value;
    }
}

/**
 * Returns the isolated hook registry.
 */
function hooks(): AdapterHooks
{
    static $hooks;

    return $hooks ??= new AdapterHooks();
}

require dirname(__DIR__) . '/libraries/Einvoice_pro_validation_exception.php';
require dirname(__DIR__) . '/libraries/Einvoice_pro_decimal.php';
require dirname(__DIR__) . '/libraries/Einvoice_pro_codes.php';
require dirname(__DIR__) . '/libraries/Einvoice_pro_document_builder.php';
require dirname(__DIR__) . '/libraries/Einvoice_pro_perfex_mapper.php';
require dirname(__DIR__) . '/libraries/Einvoice_pro_ubl_serializer.php';

$invoice = (object) [
    'id' => '42',
    'currency' => '1',
    'date' => '2026-08-18',
    'duedate' => '2026-09-17',
    'billing_street' => 'Ausland Straße 2',
    'billing_city' => 'Berlin',
    'billing_state' => 'Berlin',
    'billing_country' => '2',
    'subtotal' => '200.00',
    'discount_total' => '10.00',
    'discount_type' => 'before_tax',
    'total_tax' => '30.40',
    'adjustment' => '0.01',
    'total' => '220.41',
    'client' => (object) [
        'company' => 'Cumpărător Sintetic GmbH',
        'vat' => 'DE100000001',
        'country' => '1',
    ],
    'items' => [
        [
            'id' => '10', 'qty' => '2', 'rate' => '50.00', 'unit' => 'buc',
            'description' => 'Produs sintetic', 'long_description' => '',
        ],
        [
            'id' => '11', 'qty' => '1', 'rate' => '100.00', 'unit' => 'HUR',
            'description' => 'Serviciu sintetic', 'long_description' => '',
        ],
    ],
];

$mapper = new Einvoice_pro_perfex_mapper();
$builder = new Einvoice_pro_document_builder();
$document = $builder->build($mapper->map($invoice));
$xml = (new Einvoice_pro_ubl_serializer())->serialize($document);

$failures = [];

/**
 * Records one adapter assertion.
 *
 * @param mixed $expected
 * @param mixed $actual
 */
function adapter_same($expected, $actual, string $label): void
{
    global $failures;

    if ($expected !== $actual) {
        $failures[] = $label . ': expected ' . var_export($expected, true)
            . ', received ' . var_export($actual, true);
    }
}

adapter_same('DE', $document['buyer']['address']['country_code'], 'invoice snapshot country wins over current client country');
adapter_same('RO', $document['seller']['address']['country_code'], 'Perfex 3.4 company country code');
adapter_same(2, count($document['tax_subtotals']), 'two Perfex VAT rates');
adapter_same(2, count($document['allowances_charges']), 'discount allocated across VAT rates');
adapter_same('30.40', $document['totals']['tax_amount'], 'discounted VAT reconciliation');
adapter_same('220.41', $document['totals']['payable'], 'Perfex payable reconciliation');
adapter_same(0, substr_count($xml, '<cbc:TaxCurrencyCode>'), 'RON adapter omits tax currency');
adapter_same(true, (new DOMDocument())->loadXML($xml, LIBXML_NONET), 'adapter XML parses offline');

unset($adapterOptions['invoice_company_country_code']);
$adapterOptions['invoice_company_country'] = '1';
$legacyCountryDocument = $builder->build($mapper->map($invoice));
adapter_same('RO', $legacyCountryDocument['seller']['address']['country_code'], 'legacy country identifier remains supported');
$adapterOptions['invoice_company_country'] = '3';
$legacyArrayCountryDocument = $builder->build($mapper->map($invoice));
adapter_same('RO', $legacyArrayCountryDocument['seller']['address']['country_code'], 'legacy array country record');
$adapterOptions['invoice_company_country_code'] = 'RO';

$invoice->client->vat = '100000003';
$nonVatBuyer = $builder->build($mapper->map($invoice));
adapter_same('100000003', $nonVatBuyer['buyer']['legal_id'], 'unprefixed buyer fiscal identifier is retained');
adapter_same(null, $nonVatBuyer['buyer']['vat_id'], 'unprefixed buyer identifier is not declared as VAT');

$adapterOptions['company_vat'] = '10000001';
$adapterTaxes['10'] = [];
$invoice->items = [$invoice->items[0]];
$invoice->subtotal = '100.00';
$invoice->discount_total = '0.00';
$invoice->total_tax = '0.00';
$invoice->adjustment = '0.00';
$invoice->total = '100.00';
$nonVatDocument = $builder->build($mapper->map($invoice));
adapter_same(null, $nonVatDocument['seller']['vat_id'], 'unprefixed seller identifier is not declared as VAT');
adapter_same('O', $nonVatDocument['tax_subtotals'][0]['category'], 'non-VAT seller receives outside-scope category');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "E-Invoice RO Perfex adapter fixture passed.\n");
