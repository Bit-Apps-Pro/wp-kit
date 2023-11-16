<?php

namespace BitApps\WPKit\Helpers;

class Slug
{
    public static function generate($text)
    {
        $text = preg_replace('/[^a-zA-Z0-9]+/', '-', $text);

        return strtolower(trim($text, '-'));
    }
}
