<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Advances 2.0.0 installations after correcting module-scoped helper loading.
 */
class Migration_Version_201 extends App_module_migration
{
    /**
     * Performs no data writes because the helper-path correction changes files only.
     */
    public function up()
    {
    }
}
