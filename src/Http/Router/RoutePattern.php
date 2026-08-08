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
     * @return array<int, array{token: string, offset: int, name: string, required: bool}>
     */
    public static function placeholders(string $path): array
    {
        if (preg_match_all(self::PLACEHOLDER, $path, $matched, PREG_OFFSET_CAPTURE) === false) {
            return [];
        }

        $placeholders = [];
        $names        = [];
        foreach ($matched[0] ?? [] as [$token, $offset]) {
            $name = trim($token, '{}?');
            if (preg_match('/^[A-Za-z_]\w*$/', $name) !== 1) {
                throw new InvalidArgumentException("Invalid route parameter name [{$name}] in path [{$path}].");
            }

            if (isset($names[$name])) {
                throw new InvalidArgumentException("Duplicate route parameter [{$name}] in path [{$path}].");
            }

            $names[$name]   = true;
            $placeholders[] = [
                'token'    => $token,
                'offset'   => $offset,
                'name'     => $name,
                'required' => !str_contains($token, '?'),
            ];
        }

        return $placeholders;
    }

    /**
     * @return null|array ['regex' => string, 'params' => [name => ['required' => bool]]]; null when the path has no placeholders
     */
    public static function compile(string $path)
    {
        $placeholders = self::placeholders($path);
        if (empty($placeholders)) {
            return;
        }

        $regex  = '';
        $params = [];
        $cursor = 0;
        foreach ($placeholders as $placeholder) {
            $name          = $placeholder['name'];
            $required      = $placeholder['required'];
            $params[$name] = ['required' => $required];
            $literal       = substr($path, $cursor, $placeholder['offset'] - $cursor);
            $cursor        = $placeholder['offset'] + \strlen($placeholder['token']);

            if (!$required && str_ends_with($literal, '/')) {
                // fold the separator into the optional group so "entries" matches "entries/{slug?}"
                $regex .= self::quoteLiteral(substr($literal, 0, -1)) . "(?:\\/(?P<{$name}>[^\\/]+))?";

                continue;
            }

            $regex .= self::quoteLiteral($literal) . "(?P<{$name}>[^\\/]+)" . ($required ? '' : '?');
        }

        return ['regex' => $regex . self::quoteLiteral(substr($path, $cursor)), 'params' => $params];
    }

    private static function quoteLiteral(string $literal): string
    {
        return str_replace('/', '\/', preg_quote($literal, '~'));
    }
}
