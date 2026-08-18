<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Advances existing installations from 1.4.3 without changing their stored settings.
 */
class Migration_Version_200 extends App_module_migration
{
    /**
     * Leaves the 1.4.3 option keys and values intact while Perfex records version 2.0.0.
     */
    public function up()
    {
    }
}
