<?php

namespace BitApps\WPKit\Http\Router;

use InvalidArgumentException;

/**
 * Single source of truth for compiling {param} / {param?} route paths into named-group regexes.
 */
final class RoutePattern
{
    const PLACEHOLDER = '/\{\w+\??\}\??/';

    /**
     * @return null|array ['regex' => string, 'params' => [name => ['required' => bool]]]; null when the path has no placeholders
     */
    public static function compile(string $path)
    {
        if (preg_match_all(self::PLACEHOLDER, $path, $matched, PREG_OFFSET_CAPTURE) === false || empty($matched[0])) {
            return;
        }

        $regex  = '';
        $params = [];
        $cursor = 0;
        foreach ($matched[0] as [$placeholder, $offset]) {
            $name = trim($placeholder, '{}?');
            if (preg_match('/^[A-Za-z_]\w*$/', $name) !== 1) {
                throw new InvalidArgumentException("Invalid route parameter name [{$name}] in path [{$path}].");
            }

            if (isset($params[$name])) {
                throw new InvalidArgumentException("Duplicate route parameter [{$name}] in path [{$path}].");
            }

            $required      = strpos($placeholder, '?') === false;
            $params[$name] = ['required' => $required];
            $literal       = substr($path, $cursor, $offset - $cursor);
            $cursor        = $offset + \strlen($placeholder);

            if (!$required && substr($literal, -1) === '/') {
                // fold the separator into the optional group so "entries" matches "entries/{slug?}"
                $regex .= self::quoteLiteral(substr($literal, 0, -1)) . "(?:\\/(?P<{$name}>[^\\/]+))?";

                continue;
            }

            $regex .= self::quoteLiteral($literal) . "(?P<{$name}>[^\\/]+)" . ($required ? '' : '?');
        }

        return ['regex' => $regex . self::quoteLiteral(substr($path, $cursor)), 'params' => $params];
    }

    private static function quoteLiteral($literal)
    {
        return str_replace('/', '\/', preg_quote($literal, '~'));
    }
}
