<?php

namespace BitApps\WPKit\Cache;

use RuntimeException;

/**
 * Static forwarder to a CacheManager's default Repository, mirroring the Hooks facade style.
 *
 * @method static mixed     get(string $key, $default = null)
 * @method static bool      has(string $key)
 * @method static bool      put(string $key, $value, int $ttl)
 * @method static bool      add(string $key, $value, int $ttl)
 * @method static bool      forever(string $key, $value)
 * @method static bool      forget(string $key)
 * @method static bool      flush()
 * @method static int|false increment(string $key, int $by = 1)
 * @method static int|false decrement(string $key, int $by = 1)
 * @method static mixed     remember(string $key, int $ttl, callable $callback)
 * @method static mixed     rememberForever(string $key, callable $callback)
 * @method static mixed     pull(string $key, $default = null)
 */
final class Cache
{
    private static ?CacheManager $_manager = null;

    public static function __callStatic(string $method, array $parameters)
    {
        return self::getManager()->store()->{$method}(...$parameters);
    }

    /**
     * Set the CacheManager the facade forwards calls to; required before any other facade call.
     */
    public static function setManager(CacheManager $manager): void
    {
        self::$_manager = $manager;
    }

    /**
     * Resolve the named (or configured default) Repository from the current manager.
     */
    public static function store(?string $name = null): Repository
    {
        return self::getManager()->store($name);
    }

    /**
     * Clears the configured manager; intended for test isolation between cases.
     */
    public static function reset(): void
    {
        self::$_manager = null;
    }

    private static function getManager(): CacheManager
    {
        if (self::$_manager === null) {
            throw new RuntimeException('Cache facade used before Cache::setManager() was called.');
        }

        return self::$_manager;
    }
}
