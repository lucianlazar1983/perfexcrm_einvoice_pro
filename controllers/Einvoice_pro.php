<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Handles authorized XML downloads and administrator-managed note values.
 */
class Einvoice_pro extends AdminController
{
    /**
     * Loads only the Perfex services used by this controller.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->model('invoices_model');
        $this->load->helper('einvoice_pro/einvoice_pro');
    }

    /**
     * Returns an invoice XML file to a staff member who may view that invoice.
     *
     * Missing and inaccessible invoices use the same response to avoid exposing invoice IDs.
     *
     * @param mixed $invoice_id Route value supplied by CodeIgniter.
     */
    public function download($invoice_id = null): void
    {
        $invoiceId = einvoice_pro_positive_integer($invoice_id);
        if ($invoiceId === null) {
            show_404();
        }

        $invoice = $this->invoices_model->get($invoiceId);
        if (!$invoice || !einvoice_pro_can_view_invoice($invoice)) {
            show_404();
        }

        $xmlLanguage = (string) get_option('einvoice_pro_xml_language');
        if (in_array($xmlLanguage, ['english', 'romanian'], true)) {
            $this->lang->load('einvoice_pro', $xmlLanguage);
        }

        try {
            $xml = einvoice_pro_generate_xml($invoice);
        } catch (Einvoice_pro_validation_exception $exception) {
            log_message(
                'warning',
                'E-Invoice Pro blocked invoice ' . $invoiceId . ' with rule ' . $exception->rule()
            );
            show_error(
                _l('e_invoice_generation_blocked') . ' '
                    . einvoice_pro_validation_message($exception->rule())
                    . ' [' . $exception->rule() . ']',
                422,
                _l('e_invoice_generation_error_title')
            );
            return;
        } catch (Throwable $exception) {
            $correlationId = bin2hex(random_bytes(8));
            log_message(
                'error',
                'E-Invoice Pro generation failure ' . $correlationId . ' for invoice ' . $invoiceId
            );
            show_error(
                _l('e_invoice_generation_internal_error') . ' ' . $correlationId,
                500,
                _l('e_invoice_generation_error_title')
            );
            return;
        }

        $filename = einvoice_pro_xml_filename(format_invoice_number($invoice->id));
        $disposition = 'attachment; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode($filename);

        $this->output
            ->set_content_type('application/xml', 'utf-8')
            ->set_header('Content-Disposition: ' . $disposition)
            ->set_header('Cache-Control: private, no-store, max-age=0')
            ->set_header('Pragma: no-cache')
            ->set_header('X-Content-Type-Options: nosniff')
            ->set_output($xml);
    }

    /**
     * Saves a complete, server-validated settings payload for an administrator.
     */
    public function save_settings(): void
    {
        if (!is_admin()) {
            access_denied('E-Invoice Pro Settings');
        }
        if ($this->input->method(true) !== 'POST') {
            show_404();
        }

        $result = einvoice_pro_validate_settings($this->input->post('settings', false));
        if (!$result['valid']) {
            set_alert('danger', _l('e_invoice_settings_invalid'));
            redirect(admin_url('settings?group=einvoice_pro'));
            return;
        }

        foreach ($result['values'] as $name => $value) {
            update_option($name, $value);
        }

        set_alert('success', _l('settings_updated'));
        redirect(admin_url('settings?group=einvoice_pro'));
    }

    /**
     * Adds or removes a reusable note after validating the request and stored JSON.
     *
     * Perfex's CSRF middleware validates the POST token before this method runs.
     *
     * @param mixed $action     Route action; only add and delete are accepted.
     * @param mixed $note_index Route index; only the three released note slots are accepted.
     */
    public function manage_notes($action = null, $note_index = null): void
    {
        if (!is_admin()) {
            access_denied('E-Invoice Pro Manage Notes');
        }

        if ($this->input->method(true) !== 'POST' || !$this->input->is_ajax_request()) {
            show_404();
        }

        if (!is_string($action) || !in_array($action, ['add', 'delete'], true)) {
            $this->respondJson(false, _l('e_invoice_invalid_action'), 400);
            return;
        }

        $noteIndex = is_string($note_index) ? $note_index : (string) $note_index;
        if (!in_array($noteIndex, ['1', '2', '3'], true)) {
            $this->respondJson(false, _l('e_invoice_invalid_note_index'), 400);
            return;
        }

        $note = $this->validatedNoteValue($this->input->post('note_value', false));
        if ($note === null) {
            $this->respondJson(false, _l('e_invoice_invalid_note_value'), 422);
            return;
        }

        $optionName = 'einvoice_pro_custom_notes_' . $noteIndex;
        $notes = $this->storedNotes($optionName);
        if ($notes === null) {
            $this->respondJson(false, _l('e_invoice_notes_storage_invalid'), 500);
            return;
        }

        if ($action === 'add') {
            $this->addNote($optionName, $notes, $note);
            return;
        }

        $this->deleteNote($optionName, $notes, $note);
    }

    /**
     * Normalizes a submitted note and rejects malformed, empty, or oversized text.
     *
     * @param mixed $value Raw POST value.
     */
    private function validatedNoteValue($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '' || !einvoice_pro_is_valid_utf8($value)) {
            return null;
        }

        if (mb_strlen($value, 'UTF-8') > EINVOICE_PRO_MAX_NOTE_LENGTH) {
            return null;
        }

        return $value;
    }

    /**
     * Decodes a custom-note option without replacing malformed legacy content.
     *
     * Returning null prevents a later write from destroying data that needs manual recovery.
     *
     * @return array<int, string>|null
     */
    private function storedNotes(string $optionName): ?array
    {
        $decoded = einvoice_pro_decode_custom_notes(get_option($optionName));

        return $decoded['valid'] ? $decoded['notes'] : null;
    }

    /**
     * Persists a new note while preserving order and preventing duplicates or unbounded growth.
     *
     * @param array<int, string> $notes Existing validated notes.
     */
    private function addNote(string $optionName, array $notes, string $note): void
    {
        if (in_array($note, $notes, true)) {
            $this->respondJson(false, _l('e_invoice_value_exists'), 409);
            return;
        }

        if (count($notes) >= EINVOICE_PRO_MAX_CUSTOM_NOTES) {
            $this->respondJson(false, _l('e_invoice_note_limit_reached'), 409);
            return;
        }

        $notes[] = $note;
        if (!$this->saveNotes($optionName, $notes)) {
            $this->respondJson(false, _l('e_invoice_notes_save_failed'), 500);
            return;
        }

        $this->respondJson(true, _l('e_invoice_value_added'), 200);
    }

    /**
     * Removes one exact note value and leaves all other stored values untouched.
     *
     * @param array<int, string> $notes Existing validated notes.
     */
    private function deleteNote(string $optionName, array $notes, string $note): void
    {
        $key = array_search($note, $notes, true);
        if ($key === false) {
            $this->respondJson(false, _l('e_invoice_value_not_found'), 404);
            return;
        }

        unset($notes[$key]);
        if (!$this->saveNotes($optionName, array_values($notes))) {
            $this->respondJson(false, _l('e_invoice_notes_save_failed'), 500);
            return;
        }

        $this->respondJson(true, _l('e_invoice_value_deleted'), 200);
    }

    /**
     * Encodes note values as UTF-8 JSON before updating the matching Perfex option.
     *
     * @param array<int, string> $notes Validated note values.
     */
    private function saveNotes(string $optionName, array $notes): bool
    {
        $json = json_encode($notes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }

        update_option($optionName, $json);

        return true;
    }

    /**
     * Writes a localized JSON response with an explicit status and content type.
     */
    private function respondJson(bool $success, string $message, int $status): void
    {
        $json = json_encode(
            ['success' => $success, 'message' => $message],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );

        $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output($json === false ? '{"success":false}' : $json);
    }
}
