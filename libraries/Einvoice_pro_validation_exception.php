<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Describes a generation error that can be shown without exposing internal details.
 */
class Einvoice_pro_validation_exception extends RuntimeException
{
    private string $rule;

    /**
     * Keeps the stable rule identifier next to the human-readable explanation.
     */
    public function __construct(string $rule, string $message)
    {
        parent::__construct($message);
        $this->rule = $rule;
    }

    /**
     * Returns the internal rule identifier used by tests and safe diagnostics.
     */
    public function rule(): string
    {
        return $this->rule;
    }
}
