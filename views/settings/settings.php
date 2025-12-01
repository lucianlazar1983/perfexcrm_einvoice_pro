<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<?php echo form_open(admin_url('settings/update'), ['id' => 'einvoice-pro-settings-form']); ?>
<div class="panel_s">
    <div class="panel-body">
        <h4 class="no-margin">
            <?= _l('e_invoice_settings_title'); ?>
        </h4>
        <hr class="hr-panel-heading" />

        <?php
        // Selector pentru limba XML
        $language_options = [
            ['id' => 'romanian', 'name' => 'Română'],
            ['id' => 'english', 'name' => 'Engleză'],
        ];
        echo render_select('settings[einvoice_pro_xml_language]', $language_options, ['id', 'name'], _l('e_invoice_xml_language'), get_option('einvoice_pro_xml_language'));

        echo '<hr class="hr-panel-heading" />';

        echo render_input('settings[einvoice_pro_registration_number]', _l('e_invoice_reg_number'), get_option('einvoice_pro_registration_number'), 'text', ['placeholder' => _l('e_invoice_reg_number_placeholder')]);

        echo render_input('settings[einvoice_pro_company_legal_form]', _l('e_invoice_capital_social'), get_option('einvoice_pro_company_legal_form'), 'number');

        echo '<hr class="hr-panel-heading" />';

        echo render_input('settings[einvoice_pro_payment_iban]', _l('e_invoice_iban'), get_option('einvoice_pro_payment_iban'));

        echo render_input('settings[einvoice_pro_payment_bank_name]', _l('e_invoice_bank_name'), get_option('einvoice_pro_payment_bank_name'));

        echo '<hr class="hr-panel-heading" />';

        // Helper function to render note section
        function render_note_section($index, $label, $default_options)
        {
            $custom_notes_json = get_option('einvoice_pro_custom_notes_' . $index);
            $custom_notes = !empty($custom_notes_json) ? json_decode($custom_notes_json, true) : [];

            $all_options = $default_options;
            foreach ($custom_notes as $note) {
                $all_options[] = ['id' => $note, 'name' => $note];
            }

            echo '<div class="form-group note-section" data-index="' . $index . '">';
            echo render_select('settings[einvoice_pro_note_' . $index . ']', $all_options, ['id', 'name'], $label, get_option('einvoice_pro_note_' . $index));

            echo '<div class="manage-values-wrapper" style="margin-top: 5px;">';
            echo '<button type="button" class="btn btn-info btn-xs toggle-manage-values">' . _l('e_invoice_manage_values') . '</button>';
            echo '<div class="manage-values-container" style="display:none; margin-top: 10px; padding: 10px; background: #f9f9f9; border: 1px solid #e3e3e3;">';

            echo '<h5>' . _l('e_invoice_custom_values') . '</h5>';
            echo '<ul class="list-group custom-values-list">';
            foreach ($custom_notes as $note) {
                echo '<li class="list-group-item d-flex justify-content-between align-items-center">';
                echo '<span>' . html_escape($note) . '</span>';
                echo '<button type="button" class="btn btn-danger btn-xs delete-value" data-value="' . html_escape($note) . '"><i class="fa fa-trash"></i></button>';
                echo '</li>';
            }
            echo '</ul>';

            echo '<div class="input-group">';
            echo '<input type="text" class="form-control new-value-input" placeholder="' . _l('e_invoice_add_new_value') . '">';
            echo '<span class="input-group-btn">';
            echo '<button class="btn btn-success add-new-value" type="button"><i class="fa fa-plus"></i></button>';
            echo '</span>';
            echo '</div>';

            echo '</div>';
            echo '</div>';
            echo '</div>';
        }

        $note1_defaults = [
            ['id' => '', 'name' => _l('e_invoice_option_none')],
            ['id' => 'TVA la incasare', 'name' => _l('e_invoice_option_tva')],
        ];
        render_note_section(1, _l('e_invoice_note1'), $note1_defaults);

        $note2_defaults = [
            ['id' => '', 'name' => _l('e_invoice_option_none')],
            ['id' => 'Factura este valabila fara semnatura si stampila, conform art. 319 alin. 29 din legea 227/2015', 'name' => _l('e_invoice_option_validity')],
        ];
        render_note_section(2, _l('e_invoice_note2'), $note2_defaults);

        $note3_defaults = [
            ['id' => '', 'name' => _l('e_invoice_option_none')],
            ['id' => 'Modalitate plata -OP Bancar', 'name' => _l('e_invoice_option_payment')],
        ];
        render_note_section(3, _l('e_invoice_note3'), $note3_defaults);
        ?>

    </div>
</div>
<?php echo form_close(); ?>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Toggle manage section
        document.querySelectorAll('.toggle-manage-values').forEach(btn => {
            btn.addEventListener('click', function () {
                const container = this.nextElementSibling;
                container.style.display = container.style.display === 'none' ? 'block' : 'none';
            });
        });

        // Add new value
        document.querySelectorAll('.add-new-value').forEach(btn => {
            btn.addEventListener('click', function () {
                const wrapper = this.closest('.note-section');
                const index = wrapper.dataset.index;
                const input = wrapper.querySelector('.new-value-input');
                const value = input.value.trim();
                const list = wrapper.querySelector('.custom-values-list');
                const select = wrapper.querySelector('select');

                if (!value) return;

                $.post(admin_url + 'einvoice_pro/manage_notes/add/' + index, {
                    note_value: value,
                    [csrfData.token_name]: csrfData.hash
                }, function (response) {
                    const res = JSON.parse(response);
                    if (res.success) {
                        // Add to list
                        const li = document.createElement('li');
                        li.className = 'list-group-item d-flex justify-content-between align-items-center';
                        li.innerHTML = `<span>${value}</span><button type="button" class="btn btn-danger btn-xs delete-value" data-value="${value}"><i class="fa fa-trash"></i></button>`;
                        list.appendChild(li);

                        // Add to select
                        const option = new Option(value, value);
                        select.add(option);
                        $(select).selectpicker('refresh');

                        input.value = '';
                        alert_float('success', res.message);
                    } else {
                        alert_float('warning', res.message);
                    }
                });
            });
        });

        // Delete value
        document.addEventListener('click', function (e) {
            if (e.target.closest('.delete-value')) {
                const btn = e.target.closest('.delete-value');
                const wrapper = btn.closest('.note-section');
                const index = wrapper.dataset.index;
                const value = btn.dataset.value;
                const li = btn.closest('li');
                const select = wrapper.querySelector('select');

                if (confirm('Are you sure?')) {
                    $.post(admin_url + 'einvoice_pro/manage_notes/delete/' + index, {
                        note_value: value,
                        [csrfData.token_name]: csrfData.hash
                    }, function (response) {
                        const res = JSON.parse(response);
                        if (res.success) {
                            li.remove();
                            // Remove from select
                            for (let i = 0; i < select.options.length; i++) {
                                if (select.options[i].value === value) {
                                    select.remove(i);
                                    break;
                                }
                            }
                            $(select).selectpicker('refresh');
                            alert_float('success', res.message);
                        } else {
                            alert_float('warning', res.message);
                        }
                    });
                }
            }
        });
    });
</script>