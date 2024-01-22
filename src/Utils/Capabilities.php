<?php

namespace BitApps\WPKit\Utils;

use BitApps\WPKit\Hooks\Hooks;

final class Capabilities
{
    public static function check($cap, ...$args)
    {
        return current_user_can($cap, ...$args);
    }

    public static function filter($cap, $default = 'manage_options')
    {
        return static::check(Hooks::applyFilter($cap, $default));
    }
}
