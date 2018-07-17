<?php

namespace Amp\File;

use Amp\Coroutine;
use Amp\Parallel\Worker;
use Amp\Parallel\Worker\Pool;
use Amp\Parallel\Worker\TaskException;
use Amp\Parallel\Worker\WorkerException;
use Amp\Promise;
use Amp\Success;
use function Amp\call;

class ParallelDriver implements Driver {
    /**
     * @var \Amp\Parallel\Worker\Pool
     */
    private $pool;

    /**
     * @var Cache\Driver
     */
    private $cache;

    /**
     * @param Pool|null         $pool  [optional]
     * @param Cache\Driver|null $cache [optional] `Defaults to StatCache::getDriver()`.
     */
    public function __construct(Pool $pool = null, Cache\Driver $cache = null) {
        $this->pool  = $pool  ?? Worker\pool();
        $this->cache = $cache ?? StatCache::getDriver();
    }

    /**
     * {@inheritdoc}
     */
    public function open(string $path, string $mode): Promise {
        return call(function () use ($path, $mode) {
            $worker = $this->pool->get();
            try {
                list($id, $size, $mode) = yield $worker->enqueue(new Internal\FileTask("fopen", [$path, $mode], null, $this->cache));
            } catch (TaskException $exception) {
                throw new FilesystemException("Could not open file", $exception);
            } catch (WorkerException $exception) {
                throw new FilesystemException("Could not send open request to worker", $exception);
            }
            return new ParallelHandle($worker, $id, $path, $size, $mode, $this->cache);
        });
    }

    private function runFileTask(Internal\FileTask $task): \Generator {
        try {
            return yield $this->pool->enqueue($task);
        } catch (TaskException $exception) {
            throw new FilesystemException("The file operation failed", $exception);
        } catch (WorkerException $exception) {
            throw new FilesystemException("Could not send the file task to worker", $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function unlink(string $path): Promise {
        return call(function () use ($path) {
            $result = yield from $this->runFileTask(new Internal\FileTask("unlink", [$path], null, $this->cache));
            $this->cache->clear($path);
            return $result;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function stat(string $path): Promise {
        if ($stat = $this->cache->get($path, Cache\Driver::TYPE_STAT)) {
            return new Success($stat);
        }

        return call(function () use ($path) {
            $stat = yield from $this->runFileTask(new Internal\FileTask("stat", [$path], null, $this->cache));
            if (!empty($stat)) {
                $this->cache->set($path, $stat, Cache\Driver::TYPE_STAT);
            }
            return $stat;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function rename(string $from, string $to): Promise {
        return new Coroutine($this->runFileTask(new Internal\FileTask("rename", [$from, $to], null, $this->cache)));
    }

    /**
     * {@inheritdoc}
     */
    public function isfile(string $path): Promise {
        return call(function () use ($path) {
            $stat = yield $this->stat($path);
            if (empty($stat)) {
                return false;
            }
            if ($stat["mode"] & 0100000) {
                return true;
            }
            return false;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function isdir(string $path): Promise {
        return call(function () use ($path) {
            $stat = yield $this->stat($path);
            if (empty($stat)) {
                return false;
            }
            if ($stat["mode"] & 0040000) {
                return true;
            }
            return false;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function link(string $target, string $link): Promise {
        return new Coroutine($this->runFileTask(new Internal\FileTask("link", [$target, $link], null, $this->cache)));
    }

    /**
     * {@inheritdoc}
     */
    public function symlink(string $target, string $link): Promise {
        return new Coroutine($this->runFileTask(new Internal\FileTask("symlink", [$target, $link], null, $this->cache)));
    }

    /**
     * {@inheritdoc}
     */
    public function readlink(string $path): Promise {
        return new Coroutine($this->runFileTask(new Internal\FileTask("readlink", [$path], null, $this->cache)));
    }

    /**
     * {@inheritdoc}
     */
    public function mkdir(string $path, int $mode = 0777, bool $recursive = false): Promise {
        return new Coroutine($this->runFileTask(new Internal\FileTask("mkdir", [$path, $mode, $recursive], null, $this->cache)));
    }

    /**
     * {@inheritdoc}
     */
    public function scandir(string $path): Promise {
        return new Coroutine($this->runFileTask(new Internal\FileTask("scandir", [$path], null, $this->cache)));
    }

    /**
     * {@inheritdoc}
     */
    public function rmdir(string $path): Promise {
        return call(function () use ($path) {
            $result = yield from $this->runFileTask(new Internal\FileTask("rmdir", [$path], null, $this->cache));
            $this->cache->clear($path);
            return $result;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function chmod(string $path, int $mode): Promise {
        return new Coroutine($this->runFileTask(new Internal\FileTask("chmod", [$path, $mode], null, $this->cache)));
    }

    /**
     * {@inheritdoc}
     */
    public function chown(string $path, int $uid, int $gid): Promise {
        return new Coroutine($this->runFileTask(new Internal\FileTask("chown", [$path, $uid, $gid], null, $this->cache)));
    }

    /**
     * {@inheritdoc}
     */
    public function exists(string $path): Promise {
        return new Coroutine($this->runFileTask(new Internal\FileTask("exists", [$path], null, $this->cache)));
    }

    /**
     * {@inheritdoc}
     */
    public function size(string $path): Promise {
        return call(function () use ($path) {
            $stat = yield $this->stat($path);
            if (empty($stat)) {
                throw new FilesystemException("Specified path does not exist");
            }
            if ($stat["mode"] & 0100000) {
                return $stat["size"];
            }
            throw new FilesystemException("Specified path is not a regular file");
        });
    }

    /**
     * {@inheritdoc}
     */
    public function mtime(string $path): Promise {
        return call(function () use ($path) {
            $stat = yield $this->stat($path);
            if (empty($stat)) {
                throw new FilesystemException("Specified path does not exist");
            }
            return $stat["mtime"];
        });
    }

    /**
     * {@inheritdoc}
     */
    public function atime(string $path): Promise {
        return call(function () use ($path) {
            $stat = yield $this->stat($path);
            if (empty($stat)) {
                throw new FilesystemException("Specified path does not exist");
            }
            return $stat["atime"];
        });
    }

    /**
     * {@inheritdoc}
     */
    public function ctime(string $path): Promise {
        return call(function () use ($path) {
            $stat = yield $this->stat($path);
            if (empty($stat)) {
                throw new FilesystemException("Specified path does not exist");
            }
            return $stat["ctime"];
        });
    }

    /**
     * {@inheritdoc}
     */
    public function lstat(string $path): Promise {
        return new Coroutine($this->runFileTask(new Internal\FileTask("lstat", [$path], null, $this->cache)));
    }

    /**
     * {@inheritdoc}
     */
    public function touch(string $path, int $time = null, int $atime = null): Promise {
        return new Coroutine($this->runFileTask(new Internal\FileTask("touch", [$path, $time, $atime], null, $this->cache)));
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $path): Promise {
        return new Coroutine($this->runFileTask(new Internal\FileTask("get", [$path], null, $this->cache)));
    }

    /**
     * {@inheritdoc}
     */
    public function put(string $path, string $contents): Promise {
        return new Coroutine($this->runFileTask(new Internal\FileTask("put", [$path, $contents], null, $this->cache)));
    }
}
