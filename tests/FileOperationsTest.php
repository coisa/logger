<?php

declare(strict_types=1);

namespace CoiSA\Logger;

use CoiSA\Logger\Handler\FileOperations;
use CoiSA\Logger\Support\MemoryFileStream;
use PHPUnit\Framework\TestCase;

/** Behavioral tests of the real append boundary without physical filesystem access. */
final class FileOperationsTest extends TestCase
{
    private const DESTINATION = 'coisafileunit://records';

    /** @var bool Whether this test owns the registered protocol. */
    private bool $registered = false;

    /** @var list<resource> The handles owned by the current test. */
    private array $streams = [];

    /** @return void Registers a test-owned memory filesystem with fresh storage. */
    protected function setUp(): void
    {
        MemoryFileStream::$files = [];
        MemoryFileStream::$seekFails = false;
        $this->registered = stream_wrapper_register('coisafileunit', MemoryFileStream::class);
        self::assertTrue($this->registered);
    }

    /** @return void Closes owned handles and restores the stream-wrapper registry. */
    protected function tearDown(): void
    {
        foreach ($this->streams as $stream) {
            fclose($stream);
        }
        if ($this->registered) {
            stream_wrapper_unregister('coisafileunit');
        }
        MemoryFileStream::$files = [];
        MemoryFileStream::$seekFails = false;
    }

    /** @return void Verifies each creator chunk preserves bytes appended by another handle. */
    public function testCreatorAppendsAfterAnotherHandle(): void
    {
        $creator = $this->openStream('xb');
        $appender = $this->openStream('ab');
        $operations = new FileOperations();

        self::assertSame(2, $operations->append($creator, 'A1'));
        self::assertSame(2, $operations->append($appender, 'B1'));
        self::assertSame(2, $operations->append($creator, 'A2'));
        self::assertSame('A1B1A2', MemoryFileStream::$files[self::DESTINATION]);
    }

    /** @return void Verifies failed seeking rejects the append without changing stored bytes. */
    public function testSeekFailureDoesNotWrite(): void
    {
        $creator = $this->openStream('xb');
        $appender = $this->openStream('ab');
        $operations = new FileOperations();

        self::assertSame(2, $operations->append($creator, 'A1'));
        self::assertSame(2, $operations->append($appender, 'B1'));
        MemoryFileStream::$seekFails = true;

        self::assertFalse($operations->append($creator, 'A2'));
        self::assertSame('A1B1', MemoryFileStream::$files[self::DESTINATION]);
    }

    /** Verifies PHP accepts a digit-prefixed wrapper that private canonicalization must reject. */
    public function testRejectsRegisteredDigitPrefixedWrapper(): void
    {
        self::assertTrue(stream_wrapper_register('1x', MemoryFileStream::class));
        $stream = null;
        try {
            $stream = fopen('1x://records', 'xb');
            self::assertIsResource($stream);
            self::assertSame(6, fwrite($stream, 'marker'));
            self::assertSame('marker', MemoryFileStream::$files['1x://records']);
            $this->expectException(\UnexpectedValueException::class);
            (new FileOperations())->canonicalize('1x://records');
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            stream_wrapper_unregister('1x');
        }
    }

    /**
     * Opens an isolated stream owned by the current test.
     *
     * @param string $mode The exclusive-create or append mode.
     * @return resource The in-memory handle.
     */
    private function openStream(string $mode): mixed
    {
        $stream = fopen(self::DESTINATION, $mode);
        self::assertIsResource($stream);
        $this->streams[] = $stream;

        return $stream;
    }
}
