<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Einvoice_pro extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('invoices_model');
        $this->load->helper('einvoice_pro');
    }

    public function download($invoice_id)
    {
        if (!$invoice_id) {
            show_404();
        }

        $invoice = $this->invoices_model->get($invoice_id);

        if (!$invoice) {
            show_404();
        }

        // Încarcă fișierul de limbă selectat în setări
        $xml_lang = get_option('einvoice_pro_xml_language');
        if ($xml_lang) {
            $this->lang->load('einvoice_pro', $xml_lang);
        }

        // Generează numele fișierului XML
        $invoice_number = format_invoice_number($invoice->id);
        $file_name = str_replace('/', '-', $invoice_number) . '.xml';

        // Obține toate datele formatate corect din helper
        $data['invoice_data'] = generate_einvoice_data_for_template($invoice);

        // Setează headerele pentru a forța descărcarea
        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $file_name . '"');

        // Încarcă și afișează template-ul XML cu datele pregătite
        $this->load->view('einvoice_pro/xml/template', $data);
    }

    public function manage_notes($action, $note_index)
    {
        if (!is_admin()) {
            access_denied('E-Invoice Pro Manage Notes');
        }

        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $option_name = 'einvoice_pro_custom_notes_' . $note_index;
        $current_notes = get_option($option_name);
        $notes_array = !empty($current_notes) ? json_decode($current_notes, true) : [];

        if ($action == 'add') {
            $new_note = $this->input->post('note_value');
            if (!in_array($new_note, $notes_array)) {
                $notes_array[] = $new_note;
                update_option($option_name, json_encode($notes_array));
                echo json_encode(['success' => true, 'message' => 'Value added successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => _l('e_invoice_value_exists')]);
            }
        } elseif ($action == 'delete') {
            $note_to_delete = $this->input->post('note_value');
            if (($key = array_search($note_to_delete, $notes_array)) !== false) {
                unset($notes_array[$key]);
                update_option($option_name, json_encode(array_values($notes_array)));
                echo json_encode(['success' => true, 'message' => 'Value deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Value not found']);
            }
        }
    }
}
