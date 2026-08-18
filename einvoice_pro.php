<?php
defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: E-Invoice Pro
Description: Generates Romanian UBL e-invoice files from Perfex CRM invoices.
Version: 2.0.0
Requires at least: 3.4.1
Author: Lucian Lazar
*/

define('EINVOICE_PRO_MODULE_NAME', 'einvoice_pro');
define('EINVOICE_PRO_VERSION', '2.0.0');
define('EINVOICE_PRO_MAX_CUSTOM_NOTES', 50);
define('EINVOICE_PRO_MAX_NOTE_LENGTH', 500);

register_language_files(EINVOICE_PRO_MODULE_NAME, [EINVOICE_PRO_MODULE_NAME]);
register_activation_hook(EINVOICE_PRO_MODULE_NAME, 'einvoice_pro_activate');

hooks()->add_action('admin_init', 'einvoice_pro_module_init');
hooks()->add_action('before_invoice_preview_more_menu_button', 'einvoice_pro_invoice_button');
hooks()->add_filter('module_einvoice_pro_action_links', 'einvoice_pro_module_action_links');

/**
 * Creates the current option set without replacing values from an earlier installation.
 *
 * The function is deliberately idempotent so an interrupted activation can be retried.
 */
function einvoice_pro_activate(): void
{
    foreach (einvoice_pro_default_options() as $name => $value) {
        add_option($name, $value);
    }
}

/**
 * Returns the defaults used by clean installations.
 *
 * Existing values are never merged into this list or rewritten during activation.
 *
 * @return array<string, string>
 */
function einvoice_pro_default_options(): array
{
    return [
        'einvoice_pro_registration_number' => '',
        'einvoice_pro_payment_iban'         => '',
        'einvoice_pro_payment_bank_name'    => '',
        'einvoice_pro_company_legal_form'   => '200',
        'einvoice_pro_note_1'               => 'TVA la incasare',
        'einvoice_pro_note_2'               => 'Factura este valabila fara semnatura si stampila, conform art. 319 alin. 29 din legea 227/2015',
        'einvoice_pro_note_3'               => 'Modalitate plata -OP Bancar',
        'einvoice_pro_xml_language'         => 'romanian',
    ];
}

/**
 * Registers the settings panel and makes the module helpers available to its views.
 */
function einvoice_pro_module_init(): void
{
    $CI = &get_instance();
    $CI->load->helper('einvoice_pro');

    $CI->app->add_settings_section_child(
        'finance',
        EINVOICE_PRO_MODULE_NAME,
        [
            'name'     => _l('e_invoice_pro'),
            'view'     => 'einvoice_pro/settings/settings',
            'position' => 45,
        ]
    );
}

/**
 * Adds the download action only when the current staff member may view this invoice.
 *
 * @param object $invoice Perfex invoice passed by the preview hook.
 */
function einvoice_pro_invoice_button($invoice): void
{
    $CI = &get_instance();
    $CI->load->helper('einvoice_pro');

    if (!einvoice_pro_can_view_invoice($invoice)) {
        return;
    }

    echo $CI->load->view('einvoice_pro/buttons/invoice_button', ['invoice' => $invoice], true);
}

/**
 * Adds a settings shortcut for administrators without changing other module actions.
 *
 * @param array<int, string> $actions Existing action links supplied by Perfex.
 *
 * @return array<int, string>
 */
function einvoice_pro_module_action_links(array $actions): array
{
    if (!is_admin()) {
        return $actions;
    }

    $settings_link = '<a href="' . admin_url('settings?group=einvoice_pro') . '">' . _l('settings') . '</a>';
    array_unshift($actions, $settings_link);

    return $actions;
}
