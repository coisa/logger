<?php

declare(strict_types=1);

namespace CoiSA\Logger\Support;

// phpcs:disable PSR1.Methods.CamelCapsMethodName -- PHP defines these stream-wrapper method names.

/**
 * Stream spy for static logger creation, retaining generated records only in memory.
 */
final class MemoryLogStream
{
    /** @var resource|null PHP supplies the stream context when opening the wrapper. */
    public $context;

    /** @var string The records captured during the current unit test. */
    public static string $records = '';

    /**
     * Accepts an in-memory test stream without accessing a filesystem.
     *
     * @param string $path The synthetic destination.
     * @param string $mode The requested stream mode.
     * @param int $options PHP stream options.
     * @param string|null $openedPath The optional resolved destination.
     *
     * @return bool Always true for this isolated spy.
     */
    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return true;
    }

    /**
     * Captures generated output bytes.
     *
     * @param string $data The formatted log record.
     *
     * @return int The number of accepted bytes.
     */
    public function stream_write(string $data): int
    {
        self::$records .= $data;

        return strlen($data);
    }

    /**
     * Reports that all writes are immediately available in memory.
     *
     * @return bool Always true.
     */
    public function stream_flush(): bool
    {
        return true;
    }

    /**
     * Supplies no physical filesystem metadata.
     *
     * @return array<array-key, mixed> Empty stream metadata.
     */
    public function stream_stat(): array
    {
        return [];
    }
}
