<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('BASEPATH', __DIR__);
define('EINVOICE_PRO_CUSTOMIZATION_ID', 'urn:cen.eu:en16931:2017#compliant#urn:efactura.mfinante.ro:CIUS-RO:1.0.1');

/**
 * Supplies the UTF-8 check used by the canonical builder.
 */
function einvoice_pro_is_valid_utf8(string $value): bool
{
    return mb_check_encoding($value, 'UTF-8');
}

require dirname(__DIR__) . '/libraries/Einvoice_pro_validation_exception.php';
require dirname(__DIR__) . '/libraries/Einvoice_pro_decimal.php';
require dirname(__DIR__) . '/libraries/Einvoice_pro_codes.php';
require dirname(__DIR__) . '/libraries/Einvoice_pro_document_builder.php';
require dirname(__DIR__) . '/libraries/Einvoice_pro_ubl_serializer.php';

$failures = [];

/**
 * Records one failed equality assertion without stopping the remaining fixtures.
 *
 * @param mixed $expected
 * @param mixed $actual
 */
function fiscal_same($expected, $actual, string $label): void
{
    global $failures;

    if ($expected !== $actual) {
        $failures[] = $label . ': expected ' . var_export($expected, true)
            . ', received ' . var_export($actual, true);
    }
}

/**
 * Records one failed boolean assertion.
 */
function fiscal_true(bool $condition, string $label): void
{
    fiscal_same(true, $condition, $label);
}

/**
 * Expects a canonical validation rule from a fixture callback.
 */
function fiscal_rule(string $expectedRule, callable $callback, string $label): void
{
    global $failures;

    try {
        $callback();
        $failures[] = $label . ': expected rule ' . $expectedRule . ', no exception was thrown';
    } catch (Einvoice_pro_validation_exception $exception) {
        fiscal_same($expectedRule, $exception->rule(), $label);
    }
}

/**
 * Creates the shared synthetic party and invoice data used by positive fixtures.
 *
 * @return array<string, mixed>
 */
function fiscal_base(): array
{
    return [
        'id' => 'TEST-2026-001',
        'issue_date' => '2026-08-18',
        'due_date' => '2026-09-17',
        'type_code' => '380',
        'currency' => 'RON',
        'notes' => ['Test sintetic: <&" ăâîșț'],
        'seller' => [
            'name' => 'Furnizor Sintetic SRL',
            'legal_id' => 'J00/1/2026',
            'vat_id' => 'RO10000001',
            'address' => [
                'street' => 'Strada Exemplu 1',
                'city' => 'București',
                'subdivision' => 'B',
                'country_code' => 'RO',
            ],
        ],
        'buyer' => [
            'type' => 'business',
            'name' => 'Cumpărător Sintetic SRL',
            'legal_id' => 'J00/2/2026',
            'vat_id' => 'RO10000002',
            'address' => [
                'street' => 'Calea Test 2',
                'city' => 'Cluj-Napoca',
                'subdivision' => 'Cluj',
                'country_code' => 'RO',
            ],
        ],
        'lines' => [[
            'id' => '1',
            'quantity' => '2',
            'unit' => 'buc',
            'price_amount' => '50.00',
            'name' => 'Serviciu & produs sintetic',
            'description' => "Descriere pe două linii\nfără date reale",
            'tax' => ['category' => 'S', 'rate' => '21'],
        ]],
    ];
}

$builder = new Einvoice_pro_document_builder();
$serializer = new Einvoice_pro_ubl_serializer();

fiscal_same('0.30', Einvoice_pro_decimal::add('0.10', '0.20'), 'decimal addition');
fiscal_same('2.68', Einvoice_pro_decimal::multiply('1.005', '2.6667'), 'decimal multiplication rounding');
fiscal_same('0.67', Einvoice_pro_decimal::divide('2', '3'), 'decimal division rounding');
fiscal_same('19.00', Einvoice_pro_decimal::percentage('100', '19'), 'decimal percentage');
fiscal_rule('decimal.invalid', fn () => Einvoice_pro_decimal::normalize(1.25), 'binary float rejection');
fiscal_rule(
    'decimal.invalid',
    fn () => Einvoice_pro_decimal::normalize(str_repeat('9', 31)),
    'oversized decimal rejection'
);

$standard = $builder->build(fiscal_base());
fiscal_same('100.00', $standard['totals']['line_extension'], 'standard line total');
fiscal_same('21.00', $standard['totals']['tax_amount'], 'standard VAT total');
fiscal_same('121.00', $standard['totals']['payable'], 'standard payable total');
fiscal_same('H87', $standard['lines'][0]['unit_code'], 'common unit mapping');

$preservedNote = fiscal_base();
$preservedNote['notes'] = ["  Notă personalizată\ncu spații  "];
fiscal_same(
    "  Notă personalizată\ncu spații  ",
    $builder->build($preservedNote)['notes'][0],
    'custom note text remains unchanged'
);

$standardXml = $serializer->serialize($standard);
$standardDom = new DOMDocument();
fiscal_true($standardDom->loadXML($standardXml, LIBXML_NONET), 'standard XML parses offline');
fiscal_same(0, $standardDom->getElementsByTagNameNS('*', 'TaxCurrencyCode')->length, 'RON omits TaxCurrencyCode');
fiscal_true(strpos($standardXml, '&lt;&amp;') !== false, 'special characters are escaped by DOM');
fiscal_same(
    'Test sintetic: <&" ăâîșț',
    $standardDom->getElementsByTagNameNS('*', 'Note')->item(0)?->textContent,
    'special characters survive an XML round trip'
);

$multipleRates = fiscal_base();
$multipleRates['lines'][] = [
    'id' => '2', 'quantity' => '1', 'unit' => 'HUR', 'price_amount' => '100.00',
    'name' => 'A doua cotă', 'tax' => ['category' => 'S', 'rate' => '11'],
];
$multipleDocument = $builder->build($multipleRates);
fiscal_same(2, count($multipleDocument['tax_subtotals']), 'two VAT breakdowns');
fiscal_same('32.00', $multipleDocument['totals']['tax_amount'], 'two-rate VAT total');

$exempt = fiscal_base();
$exempt['lines'][0]['tax'] = [
    'category' => 'E', 'rate' => '0', 'exemption_reason' => 'Scutire fiscală pentru fixture sintetic',
];
$exemptDocument = $builder->build($exempt);
fiscal_same('0.00', $exemptDocument['totals']['tax_amount'], 'exempt VAT total');
fiscal_true(
    strpos($serializer->serialize($exemptDocument), 'TaxExemptionReason') !== false,
    'exemption reason serialization'
);

$reverseCharge = fiscal_base();
$reverseCharge['lines'][0]['tax'] = [
    'category' => 'AE', 'rate' => '0', 'exemption_code' => 'VATEX-EU-AE',
];
$reverseChargeDocument = $builder->build($reverseCharge);
fiscal_same('0.00', $reverseChargeDocument['totals']['tax_amount'], 'reverse-charge VAT total');

$nonVat = fiscal_base();
$nonVat['seller']['vat_id'] = null;
$nonVat['buyer']['vat_id'] = null;
$nonVat['lines'][0]['tax'] = [
    'category' => 'O', 'rate' => '0', 'exemption_code' => 'VATEX-EU-O',
];
$nonVatDocument = $builder->build($nonVat);
fiscal_same('0.00', $nonVatDocument['totals']['tax_amount'], 'non-VAT seller total');

$discount = fiscal_base();
$discount['allowances_charges'] = [[
    'charge' => false,
    'amount' => '10.00',
    'base_amount' => '100.00',
    'percentage' => '10',
    'reason' => 'Discount sintetic',
    'tax' => ['category' => 'S', 'rate' => '21'],
]];
$discount['rounding_amount'] = '0.01';
$discountDocument = $builder->build($discount);
fiscal_same('90.00', $discountDocument['totals']['tax_exclusive'], 'discount tax exclusive');
fiscal_same('18.90', $discountDocument['totals']['tax_amount'], 'discount VAT total');
fiscal_same('108.91', $discountDocument['totals']['payable'], 'discount and adjustment payable');

$foreign = fiscal_base();
$foreign['currency'] = 'EUR';
$foreign['tax_currency'] = 'RON';
$foreign['tax_amount_accounting'] = '104.50';
$foreignDocument = $builder->build($foreign);
$foreignXml = $serializer->serialize($foreignDocument);
fiscal_same(1, substr_count($foreignXml, '<cbc:TaxCurrencyCode>RON</cbc:TaxCurrencyCode>'), 'foreign tax currency');
fiscal_same(2, substr_count($foreignXml, '<cac:TaxTotal>'), 'accounting VAT creates second tax total');

$b2c = fiscal_base();
$b2c['buyer']['type'] = 'individual_unidentified';
$b2c['buyer']['legal_id'] = null;
$b2c['buyer']['vat_id'] = null;
$b2cDocument = $builder->build($b2c);
fiscal_same('0000000000000', $b2cDocument['buyer']['legal_id'], 'effective-dated B2C identifier');

$sameTaxCurrency = fiscal_base();
$sameTaxCurrency['tax_currency'] = 'RON';
$sameTaxCurrency['tax_amount_accounting'] = '21.00';
fiscal_rule('currency.tax_same', fn () => $builder->build($sameTaxCurrency), 'same tax currency rejection');

$foreignWithoutAccountingVat = fiscal_base();
$foreignWithoutAccountingVat['currency'] = 'EUR';
fiscal_rule(
    'currency.accounting_required',
    fn () => $builder->build($foreignWithoutAccountingVat),
    'Romanian foreign-currency accounting VAT requirement'
);

$missingDueDate = fiscal_base();
$missingDueDate['due_date'] = null;
fiscal_rule('payment.due', fn () => $builder->build($missingDueDate), 'missing due date rejection');

$unknownUnit = fiscal_base();
$unknownUnit['lines'][0]['unit'] = 'cutie misterioasă';
fiscal_rule('unit.unsupported', fn () => $builder->build($unknownUnit), 'unknown unit rejection');

$negativeQuantity = fiscal_base();
$negativeQuantity['lines'][0]['quantity'] = '-1';
fiscal_rule('line.quantity', fn () => $builder->build($negativeQuantity), 'negative quantity rejection');

$outsideScope = fiscal_base();
$outsideScope['lines'][0]['tax'] = [
    'category' => 'O', 'rate' => '0', 'exemption_code' => 'VATEX-EU-O',
];
fiscal_rule(
    'tax.outside_scope_identifiers',
    fn () => $builder->build($outsideScope),
    'outside-scope VAT identifier rejection'
);

$reverseWithoutBuyerVat = $reverseCharge;
$reverseWithoutBuyerVat['buyer']['vat_id'] = null;
fiscal_rule(
    'tax.reverse_charge_identifiers',
    fn () => $builder->build($reverseWithoutBuyerVat),
    'reverse-charge buyer VAT rejection'
);

$standardWithoutSellerVat = fiscal_base();
$standardWithoutSellerVat['seller']['vat_id'] = null;
fiscal_rule(
    'tax.standard_seller',
    fn () => $builder->build($standardWithoutSellerVat),
    'standard VAT seller identifier rejection'
);

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "E-Invoice Pro fiscal fixtures passed.\n");
