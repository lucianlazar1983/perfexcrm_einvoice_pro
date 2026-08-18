<?php

defined('BASEPATH') or exit('No direct script access allowed');

$settings = einvoice_pro_settings_data();
?>

<link rel="stylesheet" href="<?= html_escape(module_dir_url('einvoice_pro', 'assets/css/settings.css')); ?>">

<?php echo form_open(admin_url('settings/update'), ['id' => 'einvoice-pro-settings-form']); ?>
<div
    class="panel_s"
    id="einvoice-pro-settings"
    data-notes-endpoint="<?= html_escape(admin_url('einvoice_pro/manage_notes/')); ?>"
    data-confirm-message="<?= html_escape(_l('e_invoice_delete_confirmation')); ?>"
    data-delete-label="<?= html_escape(_l('e_invoice_delete_value')); ?>"
    data-request-failed="<?= html_escape(_l('e_invoice_request_failed')); ?>"
>
    <div class="panel-body">
        <h4 class="no-margin"><?= html_escape(_l('e_invoice_settings_title')); ?></h4>
        <hr class="hr-panel-heading">

        <?php
        echo render_select(
            'settings[einvoice_pro_xml_language]',
            $settings['languages'],
            ['id', 'name'],
            _l('e_invoice_xml_language'),
            $settings['xml_language']
        );
        ?>

        <hr class="hr-panel-heading">

        <?php
        echo render_input(
            'settings[einvoice_pro_registration_number]',
            _l('e_invoice_reg_number'),
            $settings['registration'],
            'text',
            ['placeholder' => _l('e_invoice_reg_number_placeholder')]
        );
        echo render_input(
            'settings[einvoice_pro_company_legal_form]',
            _l('e_invoice_capital_social'),
            $settings['company_legal_form'],
            'number'
        );
        ?>

        <hr class="hr-panel-heading">

        <?php
        echo render_input(
            'settings[einvoice_pro_payment_iban]',
            _l('e_invoice_iban'),
            $settings['payment_iban']
        );
        echo render_input(
            'settings[einvoice_pro_payment_bank_name]',
            _l('e_invoice_bank_name'),
            $settings['payment_bank_name']
        );
        ?>

        <hr class="hr-panel-heading">

        <?php foreach ($settings['note_sections'] as $section): ?>
            <div class="form-group note-section" data-index="<?= html_escape($section['index']); ?>">
                <?php
                echo render_select(
                    'settings[einvoice_pro_note_' . $section['index'] . ']',
                    $section['options'],
                    ['id', 'name'],
                    $section['label'],
                    $section['selected']
                );
                ?>

                <?php if (!$section['storage_valid']): ?>
                    <div class="alert alert-warning">
                        <?= html_escape(_l('e_invoice_notes_storage_review')); ?>
                    </div>
                <?php endif; ?>

                <div class="manage-values-wrapper">
                    <button type="button" class="btn btn-info btn-xs toggle-manage-values">
                        <?= html_escape(_l('e_invoice_manage_values')); ?>
                    </button>

                    <div class="manage-values-container" hidden>
                        <h5><?= html_escape(_l('e_invoice_custom_values')); ?></h5>
                        <ul class="list-group custom-values-list">
                            <?php foreach ($section['custom_notes'] as $note): ?>
                                <li class="list-group-item einvoice-pro-note-row">
                                    <span class="einvoice-pro-note-text"><?= html_escape($note); ?></span>
                                    <button
                                        type="button"
                                        class="btn btn-danger btn-xs delete-value"
                                        data-value="<?= html_escape($note); ?>"
                                        aria-label="<?= html_escape(_l('e_invoice_delete_value')); ?>"
                                        title="<?= html_escape(_l('e_invoice_delete_value')); ?>"
                                    >
                                        <i class="fa fa-trash" aria-hidden="true"></i>
                                    </button>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <div class="input-group">
                            <input
                                type="text"
                                class="form-control new-value-input"
                                maxlength="<?= (int) EINVOICE_PRO_MAX_NOTE_LENGTH; ?>"
                                placeholder="<?= html_escape(_l('e_invoice_add_new_value')); ?>"
                            >
                            <span class="input-group-btn">
                                <button
                                    class="btn btn-success add-new-value"
                                    type="button"
                                    aria-label="<?= html_escape(_l('e_invoice_add_new_value')); ?>"
                                    title="<?= html_escape(_l('e_invoice_add_new_value')); ?>"
                                >
                                    <i class="fa fa-plus" aria-hidden="true"></i>
                                </button>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php echo form_close(); ?>

<script src="<?= html_escape(module_dir_url('einvoice_pro', 'assets/js/settings.js')); ?>" defer></script>
