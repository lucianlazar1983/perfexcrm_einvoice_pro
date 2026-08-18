<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('BASEPATH', __DIR__);

$einvoiceProTestStaffLoggedIn = true;
$einvoiceProTestStaffId = 7;
$einvoiceProTestCapabilities = [];
$einvoiceProTestOptions = [];
$einvoiceProTestActivation = null;

/**
 * Minimal hook registry used to load the module bootstrap outside Perfex.
 */
class EinvoiceProTestHooks
{
    /**
     * Accepts action registrations because their runtime behavior is not part of this smoke test.
     */
    public function add_action(string $tag, string $callback): void
    {
    }

    /**
     * Accepts filter registrations because their runtime behavior is not part of this smoke test.
     */
    public function add_filter(string $tag, string $callback): void
    {
    }
}

/**
 * Supplies a stable hook registry to the module bootstrap.
 */
function hooks(): EinvoiceProTestHooks
{
    static $hooks;

    if (!$hooks) {
        $hooks = new EinvoiceProTestHooks();
    }

    return $hooks;
}

/**
 * Accepts language registration while loading the bootstrap in isolation.
 *
 * @param array<int, string> $files Registered language file names.
 */
function register_language_files(string $module, array $files): void
{
}

/**
 * Captures the activation callback so clean-install and upgrade behavior can be exercised.
 */
function register_activation_hook(string $module, string $callback): void
{
    global $einvoiceProTestActivation;

    $einvoiceProTestActivation = $callback;
}

/**
 * Mirrors Perfex add-option semantics by leaving an existing value untouched.
 *
 * @param mixed $value Default value for a missing option.
 */
function add_option(string $name, $value): void
{
    global $einvoiceProTestOptions;

    if (!array_key_exists($name, $einvoiceProTestOptions)) {
        $einvoiceProTestOptions[$name] = $value;
    }
}

/**
 * Supplies the authentication state required by the permission helper.
 */
function is_staff_logged_in(): bool
{
    global $einvoiceProTestStaffLoggedIn;

    return $einvoiceProTestStaffLoggedIn;
}

/**
 * Supplies capability decisions without loading Perfex.
 */
function staff_can(string $capability, string $feature): bool
{
    global $einvoiceProTestCapabilities;

    return $feature === 'invoices' && !empty($einvoiceProTestCapabilities[$capability]);
}

/**
 * Supplies a stable staff ID for view-own checks.
 */
function get_staff_user_id(): int
{
    global $einvoiceProTestStaffId;

    return $einvoiceProTestStaffId;
}

/**
 * Records a strict comparison failure and continues so one run reports all smoke failures.
 *
 * @param mixed $expected Expected value.
 * @param mixed $actual   Actual value.
 */
function einvoice_pro_test_same($expected, $actual, string $label): void
{
    global $einvoiceProTestFailures;

    if ($expected === $actual) {
        return;
    }

    $einvoiceProTestFailures[] = $label . ': expected ' . var_export($expected, true)
        . ', received ' . var_export($actual, true);
}

require dirname(__DIR__) . '/einvoice_pro.php';
require dirname(__DIR__) . '/helpers/einvoice_pro_helper.php';

$einvoiceProTestFailures = [];

$bootstrapSource = file_get_contents(dirname(__DIR__) . '/einvoice_pro.php');
$controllerSource = file_get_contents(dirname(__DIR__) . '/controllers/Einvoice_pro.php');
einvoice_pro_test_same(
    2,
    substr_count((string) $bootstrapSource, "load->helper('einvoice_pro/einvoice_pro')"),
    'module-scoped bootstrap helper paths'
);
einvoice_pro_test_same(
    1,
    substr_count((string) $controllerSource, "load->helper('einvoice_pro/einvoice_pro')"),
    'module-scoped controller helper path'
);

einvoice_pro_test_same(12, einvoice_pro_positive_integer('12'), 'positive numeric route value');
einvoice_pro_test_same(null, einvoice_pro_positive_integer('012'), 'leading zero route value');
einvoice_pro_test_same(null, einvoice_pro_positive_integer("1\r\nX-Test: yes"), 'header-shaped route value');
einvoice_pro_test_same(null, einvoice_pro_positive_integer('-1'), 'negative route value');

$safeFilename = einvoice_pro_xml_filename("INV/2026\r\nX-Test: yes");
einvoice_pro_test_same('INV-2026-X-Test-yes.xml', $safeFilename, 'download filename normalization');

$encodedNotes = json_encode(['Notă ăâîșț', '<&"'], JSON_UNESCAPED_UNICODE);
$decodedNotes = einvoice_pro_decode_custom_notes($encodedNotes);
einvoice_pro_test_same(true, $decodedNotes['valid'], 'valid custom-note JSON');
einvoice_pro_test_same(['Notă ăâîșț', '<&"'], $decodedNotes['notes'], 'custom-note text preservation');
einvoice_pro_test_same(false, einvoice_pro_decode_custom_notes('{broken')['valid'], 'invalid custom-note JSON');

$invoice = (object) ['addedfrom' => 7, 'sale_agent' => 0];
$einvoiceProTestCapabilities = ['view_own' => true];
einvoice_pro_test_same(true, einvoice_pro_can_view_invoice($invoice), 'creator view-own access');

$invoice = (object) ['addedfrom' => 8, 'sale_agent' => 9];
einvoice_pro_test_same(false, einvoice_pro_can_view_invoice($invoice), 'unrelated view-own denial');

$invoice = (object) ['addedfrom' => 8, 'sale_agent' => 7];
einvoice_pro_test_same(true, einvoice_pro_can_view_invoice($invoice), 'assigned agent view-own access');

$einvoiceProTestCapabilities = ['view' => true];
einvoice_pro_test_same(true, einvoice_pro_can_view_invoice((object) []), 'global invoice view access');

$fixturePath = __DIR__ . '/fixtures/legacy-options-1.4.3.json';
$fixture = json_decode((string) file_get_contents($fixturePath), true);
einvoice_pro_test_same(JSON_ERROR_NONE, json_last_error(), 'legacy option fixture JSON');

$expectedLegacyKeys = [
    'einvoice_pro_registration_number',
    'einvoice_pro_payment_iban',
    'einvoice_pro_payment_bank_name',
    'einvoice_pro_company_legal_form',
    'einvoice_pro_note_1',
    'einvoice_pro_note_2',
    'einvoice_pro_note_3',
    'einvoice_pro_xml_language',
    'einvoice_pro_custom_notes_1',
    'einvoice_pro_custom_notes_2',
    'einvoice_pro_custom_notes_3',
];
einvoice_pro_test_same($expectedLegacyKeys, array_keys($fixture), 'legacy option fixture coverage');

$einvoiceProTestOptions = $fixture;
$optionsBeforeUpgrade = $einvoiceProTestOptions;
call_user_func($einvoiceProTestActivation);
einvoice_pro_test_same($optionsBeforeUpgrade, $einvoiceProTestOptions, 'activation preserves every 1.4.3 option');

$einvoiceProTestOptions = [];
call_user_func($einvoiceProTestActivation);
einvoice_pro_test_same(einvoice_pro_default_options(), $einvoiceProTestOptions, 'clean activation creates current defaults');

/**
 * Provides the parent contract needed to load the Perfex migration in isolation.
 */
class App_module_migration
{
}

require dirname(__DIR__) . '/migrations/200_version_200.php';
require dirname(__DIR__) . '/migrations/201_version_201.php';

$einvoiceProTestOptions = $fixture;
$optionsBeforeMigration = $einvoiceProTestOptions;
$migration = new Migration_Version_200();
$migration->up();
einvoice_pro_test_same($optionsBeforeMigration, $einvoiceProTestOptions, 'migration 200 preserves every 1.4.3 option');

$migration = new Migration_Version_201();
$migration->up();
einvoice_pro_test_same($optionsBeforeMigration, $einvoiceProTestOptions, 'migration 201 preserves every 1.4.3 option');

$lang = [];
require dirname(__DIR__) . '/language/english/einvoice_pro_lang.php';
$englishLanguageKeys = array_keys($lang);

$lang = [];
require dirname(__DIR__) . '/language/romanian/einvoice_pro_lang.php';
$romanianLanguageKeys = array_keys($lang);
einvoice_pro_test_same($englishLanguageKeys, $romanianLanguageKeys, 'English and Romanian language-key parity');

if ($einvoiceProTestFailures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $einvoiceProTestFailures) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "E-Invoice Pro smoke checks passed.\n");
