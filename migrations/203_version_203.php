<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Advances existing installations to the Perfex 3.4 country-code compatibility patch.
 */
class Migration_Version_203 extends App_module_migration
{
    /**
     * Keeps the migration sequence complete; this patch does not change stored module data.
     */
    public function up()
    {
    }
}
