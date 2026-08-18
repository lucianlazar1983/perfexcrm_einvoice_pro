<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Advances existing installations to the reconciled 2.0.2 generation pipeline.
 */
class Migration_Version_202 extends App_module_migration
{
    /**
     * Adds the explicit fallback unit without changing any setting released before 2.0.2.
     */
    public function up()
    {
        add_option('einvoice_pro_default_unit_code', 'H87');
    }
}
