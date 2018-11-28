<?php

declare(strict_types=1);

namespace Amp\File\Cache;

interface Driver
{
    const TYPE_REALPATH = 'realpath';
    const TYPE_STAT = 'stat';
    const TYPE_LSTAT = 'lstat';

    /**
     * Retrieves an item from the cache or NULL if it is not set or expired.
     *
     * @param string $path
     * @param string $type
     *
     * @return array|null
     */
    public function get(string $path, string $type);

    /**
     * Stores an item in the cache.
     *
     * @param string $path
     * @param array $data
     * @param string $type
     */
    public function set(string $path, array $data, string $type);

    /**
     * Returns the cache ttl in seconds.
     *
     * @return int
     */
    public function getTtl(): int;

    /**
     * Sets the cache ttl in seconds. This will only affect new files, use
     * clear to reset the cache if you require it.
     *
     * @param int $ttl
     */
    public function setTtl(int $ttl);

    /**
     * Removes one or more items from the cache, leave all arguments empty
     * to reset the cache.
     *
     * @param string|null $path
     * @param string|null $type
     */
    public function clear(string $path = null, string $type = null);

    /**
     * Removes old items from the cache (i.e. due to ttl expiry).
     */
    public function cleanup();
}
