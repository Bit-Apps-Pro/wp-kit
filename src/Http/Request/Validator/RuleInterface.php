<?php

namespace BitApps\WPKit\Http\Request\Validator;

interface RuleInterface
{
    /**
     * Checks if value validate the rule
     *
     * @param string $value
     *
     * @return bool
     */
    public function validate($value);

    public function message();
}
