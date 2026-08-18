<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<a href="<?= html_escape(admin_url('einvoice_pro/download/' . (int) $invoice->id)); ?>" class="btn btn-default">
    <i class="fa-solid fa-file-code" aria-hidden="true"></i>
    <?= html_escape(_l('e_invoice_button_text')); ?>
</a>
