<?php

declare(strict_types=1);

namespace CoiSA\Logger\Support;

// phpcs:disable PSR1.Methods.CamelCapsMethodName -- PHP defines these stream-wrapper method names.

/** Shared in-memory files with independent cursors for native append boundary tests. */
final class MemoryFileStream
{
    /** @var resource|null PHP supplies the stream context when opening the wrapper. */
    public $context;

    /** @var array<string, string> Synthetic destinations and their stored bytes. */
    public static array $files = [];

    /** @var bool Whether the current test rejects seeking. */
    public static bool $seekFails = false;

    /** @var string The synthetic destination associated with this handle. */
    private string $path;

    /** @var int This handle's independent byte offset. */
    private int $position = 0;

    /** @var bool Whether this handle writes in append mode. */
    private bool $append = false;

    /**
     * Opens an exclusively created or append-only synthetic file.
     *
     * @param string $path The in-memory destination.
     * @param string $mode The requested stream mode.
     * @param int $options PHP stream options.
     * @param string|null $openedPath The optional resolved destination.
     * @return bool Whether the requested open succeeds.
     */
    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        if (!in_array($mode, ['xb', 'ab'], true)) {
            return false;
        }
        if ($mode === 'xb' && array_key_exists($path, self::$files)) {
            return false;
        }
        self::$files[$path] ??= '';
        $this->path = $path;
        $this->append = $mode === 'ab';
        $this->position = $this->append ? strlen(self::$files[$path]) : 0;

        return true;
    }

    /**
     * Writes at this handle's cursor, or at the current EOF for append mode.
     *
     * @param string $data The bytes to write.
     * @return int The accepted byte count.
     */
    public function stream_write(string $data): int
    {
        if ($this->append) {
            $this->position = strlen(self::$files[$this->path]);
        }
        $bytes = str_pad(self::$files[$this->path], $this->position, "\0");
        self::$files[$this->path] = substr_replace($bytes, $data, $this->position, strlen($data));
        $this->position += strlen($data);

        return strlen($data);
    }

    /**
     * Moves only this handle's cursor against the current shared contents.
     *
     * @param int $offset The requested byte displacement.
     * @param int $whence The origin of the displacement.
     * @return bool Whether the seek succeeds.
     */
    public function stream_seek(int $offset, int $whence): bool
    {
        if (self::$seekFails) {
            return false;
        }
        $position = match ($whence) {
            SEEK_SET => $offset,
            SEEK_CUR => $this->position + $offset,
            SEEK_END => strlen(self::$files[$this->path]) + $offset,
            default => -1,
        };
        if ($position < 0) {
            return false;
        }
        $this->position = $position;

        return true;
    }

    /** @return int This handle's byte offset. */
    public function stream_tell(): int
    {
        return $this->position;
    }

    /** @return bool Whether all stored bytes are immediately available. */
    public function stream_flush(): bool
    {
        return true;
    }
}
