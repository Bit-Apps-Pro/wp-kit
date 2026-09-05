<?php

namespace BitApps\WPKit\Cache\Contracts;

/**
 * Minimal cache backend contract: a get/put/forget key-value store with TTL support.
 */
interface Store
{
    /**
     * Retrieve an item, or null if it is missing or expired.
     *
     * @return mixed
     */
    public function get(string $key);

    /**
     * Store an item for the given number of seconds.
     *
     * @param mixed $value
     */
    public function put(string $key, $value, int $ttl): bool;

    /**
     * Store an item only if it doesn't already exist (or has expired).
     *
     * @param mixed $value
     */
    public function add(string $key, $value, int $ttl): bool;

    /**
     * Store an item indefinitely.
     *
     * @param mixed $value
     */
    public function forever(string $key, $value): bool;

    /**
     * Remove an item from the store.
     */
    public function forget(string $key): bool;

    /**
     * Remove all items from the store.
     */
    public function flush(): bool;

    /**
     * Increment a stored integer value and return the new value.
     *
     * @return int|false
     */
    public function increment(string $key, int $by = 1);

    /**
     * Decrement a stored integer value and return the new value.
     *
     * @return int|false
     */
    public function decrement(string $key, int $by = 1);
}
