<?php

namespace Amp\File\Test;

use Amp\File;

abstract class AsyncHandleTest extends HandleTest
{
    /**
     * @expectedException \Amp\File\PendingOperationError
     */
    public function testSimultaneousReads()
    {
        $this->execute(function () {
            /** @var \Amp\File\Handle $handle */
            $handle = yield File\open(__FILE__, "r");

            $promise1 = $handle->read();
            $promise2 = $handle->read();

            $expected = \substr(yield File\get(__FILE__), 0, 20);
            $this->assertSame($expected, yield $promise1);

            yield $promise2;
        });
    }

    /**
     * @expectedException \Amp\File\PendingOperationError
     */
    public function testSeekWhileReading()
    {
        $this->execute(function () {
            /** @var \Amp\File\Handle $handle */
            $handle = yield File\open(__FILE__, "r");

            $promise1 = $handle->read(10);
            $promise2 = $handle->seek(0);

            $expected = \substr(yield File\get(__FILE__), 0, 10);
            $this->assertSame($expected, yield $promise1);

            yield $promise2;
        });
    }

    /**
     * @expectedException \Amp\File\PendingOperationError
     */
    public function testReadWhileWriting()
    {
        $this->execute(function () {
            /** @var \Amp\File\Handle $handle */
            $handle = yield File\open(__FILE__, "r");

            $data = "test";

            $promise1 = $handle->write($data);
            $promise2 = $handle->read(10);

            $this->assertSame(\strlen($data), yield $promise1);

            yield $promise2;
        });
    }

    /**
     * @expectedException \Amp\File\PendingOperationError
     */
    public function testWriteWhileReading()
    {
        $this->execute(function () {
            /** @var \Amp\File\Handle $handle */
            $handle = yield File\open(__FILE__, "r");

            $promise1 = $handle->read(10);
            $promise2 = $handle->write("test");

            $expected = \substr(yield File\get(__FILE__), 0, 10);
            $this->assertSame($expected, yield $promise1);

            yield $promise2;
        });
    }
}
