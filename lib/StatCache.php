<?php

namespace Amp\File;

use Amp\Loop;

/**
 * @deprecated
 */
class StatCache
{
    const DEFAULT_TTL = 3;

    /**
     * @var Cache\Driver|null
     */
    private static $driver;

    private static function init()
    {
        $watcher = Loop::repeat(1000, function () {
            self::getDriver()->cleanup();
        });

        Loop::unreference($watcher);

        Loop::setState(self::class, new class($watcher) {
            private $watcher;
            private $driver;

            public function __construct(string $watcher)
            {
                $this->watcher = $watcher;
                $this->driver = Loop::get();
            }

            public function __destruct()
            {
                $this->driver->cancel($this->watcher);
            }
        });
    }

    public static function getDriver(): Cache\Driver
    {
        if (!self::$driver) {
            self::$driver = new Cache\ArrayDriver(self::DEFAULT_TTL);
        }

        return self::$driver;
    }

    public static function setDriver(Cache\Driver $driver)
    {
        self::$driver = $driver;
    }

    public static function get(string $path)
    {
        return self::getDriver()->get($path, Cache\Driver::TYPE_STAT);
    }

    public static function set(string $path, array $stat)
    {
        if (self::getDriver()->getTtl() <= 0) {
            return;
        }

        if (Loop::getState(self::class) === null) {
            self::init();
        }

        self::getDriver()->set($path, $stat, Cache\Driver::TYPE_STAT);
    }

    public static function ttl(int $seconds)
    {
        self::getDriver()->setTtl($seconds);
    }

    public static function clear(string $path = null)
    {
        self::getDriver()->clear($path);
    }
}
