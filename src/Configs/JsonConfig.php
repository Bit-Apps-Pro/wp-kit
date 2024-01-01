<?php

namespace BitApps\WPKit\Configs;

final class JsonConfig
{
    protected static $decodeAsArray = true;

    public static function setDecodeAsArray($value)
    {
        static::$decodeAsArray = $value;
    }

    public static function decodeAsArray()
    {
        return static::$decodeAsArray;
    }
}
