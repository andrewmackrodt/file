<?php

declare(strict_types=1);

namespace Amp\File\Cache;

class ArrayDriver implements Driver {
    /**
     * @var array
     */
    private $cache = [];

    /**
     * @var int
     */
    private $ttl;

    /**
     * @param int|null $ttl
     */
    public function __construct(int $ttl = null) {
        if ($ttl === null) {
            $ttl = (int) ini_get('realpath_cache_ttl');
        }

        $this->ttl = $ttl;
    }

    public function get(string $path, string $type) {
        if (empty($this->cache[$path][$type])) {
            return null;
        }

        $item = $this->cache[$path][$type];

        // remove the item from the cache if it's expired
        if (\time() > $item['expiration']) {
            $item = null;

            unset($this->cache[$path][$type]);

            if (empty($this->cache[$path])) {
                unset($this->cache[$path]);
            }

            return null;
        }

        return $item['data'];
    }

    public function set(string $path, array $data, string $type) {
        if ($this->ttl === 0) {
            return;
        }

        $this->cache[$path][$type] = [
            'data' => $data,
            'expiration' => \time() + $this->getTtl(),
        ];
    }

    public function getTtl(): int {
        return $this->ttl;
    }

    public function setTtl(int $ttl) {
        $this->ttl = $ttl;
    }

    public function clear(string $path = null, string $type = null) {
        if ($path !== null) {
            if (!isset($this->cache[$path])) {
                return;
            }

            $scope = [$path];
        } else {
            $scope = array_keys($this->cache);
        }

        if ($type === null) {
            $this->cache = [];

            return;
        }

        foreach ($scope as $path) {
            unset($this->cache[$path][$type]);
        }
    }

    public function cleanup() {
        $now = \time();

        foreach ($this->cache as $path => $item) {
            foreach ([self::TYPE_REALPATH, self::TYPE_STAT, self::TYPE_LSTAT] as $type) {
                if ($now > ($item['expiration'] ?? 0)) {
                    unset($this->cache[$path][$type]);
                }
            }

            if (empty($item)) {
                unset($this->cache[$path]);
            }
        }
    }
}
