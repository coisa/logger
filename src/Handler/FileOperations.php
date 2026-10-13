<?php

/**
 * This file is part of coisa/logger.
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 *
 * @link      https://github.com/coisa/logger
 * @link      https://12factor.net/logs
 *
 * @copyright Copyright (c) 2022-2026 Felipe Sayão Lobato Abreu <github@mentordosnerds.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace CoiSA\Logger\Handler;

/**
 * Native local-file operations used by the private log handler.
 *
 * This boundary owns syscall invocation, not destination policy. It MUST NOT
 * create parent directories or silently substitute another output destination.
 */
class FileOperations
{
    /**
     * Resolves existing parent aliases without following the final destination.
     *
     * @param string $filename A local path without a stream scheme.
     * @return string The absolute destination under an existing canonical parent.
     * @throws \UnexpectedValueException When the parent cannot be resolved.
     */
    public function canonicalize(string $filename): string
    {
        $parent = realpath(dirname($filename));
        if ($parent === false) {
            throw new \UnexpectedValueException('The private log parent directory must already exist.');
        }
        return $parent . DIRECTORY_SEPARATOR . basename($filename);
    }

    /**
     * Reads fresh metadata without following the final symlink.
     *
     * @param string $path The destination.
     * @return array|false Native lstat fields, or failure/missing destination.
     */
    public function metadata(string $path): array|false
    {
        clearstatcache(true, $path);
        return @lstat($path);
    }

    /**
     * Reads metadata for the already canonicalized parent.
     *
     * @param string $path The destination.
     * @return array|false Native metadata for its parent.
     */
    public function parentMetadata(string $path): array|false
    {
        return $this->metadata(dirname($path));
    }

    /**
     * Opens a local file lazily, creating exclusively with private initial permissions.
     *
     * @param string $path The validated destination.
     * @param bool $create Whether the preflight found no destination.
     * @return resource|false The open descriptor, or failure.
     */
    public function open(string $path, bool $create): mixed
    {
        if (!$create) {
            return @fopen($path, 'ab');
        }
        $mask = umask(0077);
        try {
            return @fopen($path, 'xb');
        } finally {
            umask($mask);
        }
    }

    /**
     * Requests private permissions without treating success as descriptor proof.
     *
     * @param string $path The destination.
     * @return bool Whether chmod succeeded; callers MUST verify the descriptor too.
     */
    public function restrict(string $path): bool
    {
        return @chmod($path, 0600);
    }

    /**
     * Reads actual descriptor metadata independently of the path.
     *
     * @param resource $stream The open descriptor.
     * @return array|false Native fstat fields, or failure.
     */
    public function descriptorMetadata(mixed $stream): array|false
    {
        return @fstat($stream);
    }

    /**
     * Acquires or releases an exclusive advisory lock.
     *
     * @param resource $stream The descriptor.
     * @param bool $acquire Whether to acquire or release.
     * @return bool The lock result.
     */
    public function lock(mixed $stream, bool $acquire): bool
    {
        return @flock($stream, $acquire ? LOCK_EX : LOCK_UN);
    }

    /**
     * Appends one chunk; policy and partial-write handling belong to the handler.
     *
     * @param resource $stream The descriptor.
     * @param string $bytes Formatted data.
     * @return int|false Bytes written or failure.
     */
    public function append(mixed $stream, string $bytes): int|false
    {
        return @fwrite($stream, $bytes);
    }

    /**
     * Closes the descriptor owned by the handler.
     *
     * @param resource $stream The owned descriptor.
     * @return void
     */
    public function close(mixed $stream): void
    {
        @fclose($stream);
    }
}
