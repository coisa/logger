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

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Appends only to a regular single-link file with effective mode 0600.
 *
 * The parent MUST be application-controlled and not group/world-writable.
 * This is not a sandbox against malicious same-UID filesystem mutation.
 * Parent aliases are canonicalized; final symlinks MUST never be followed.
 */
final class PrivateFileHandler extends AbstractProcessingHandler
{
    /** @var resource|null The owned open descriptor, closed on every policy failure. */
    private mixed $stream = null;

    /** @var string|null The canonical path associated with the open descriptor. */
    private ?string $path = null;

    /**
     * Constructs a lazy private-file sink without accessing the filesystem.
     *
     * @param string $filename The local destination, including a valid relative filename such as "0".
     * @param int|string|Level $level The minimum Monolog logging level.
     * @param FileOperations $operations The concrete native operations boundary.
     * @throws \InvalidArgumentException When the destination is blank, contains NUL, or names a stream.
     */
    public function __construct(
        private readonly string $filename,
        int|string|Level $level = Level::Debug,
        private readonly FileOperations $operations = new FileOperations(),
    ) {
        if (
            trim($filename) === '' || str_contains($filename, "\0")
            || preg_match('/^[a-z][a-z0-9+.-]*:/i', $filename)
        ) {
            throw new \InvalidArgumentException('A private log destination must be a local file path.');
        }
        parent::__construct($level);
    }

    /**
     * Verifies policy before every write, including partial-write retries and reopen.
     *
     * @param LogRecord $record The already formatted record.
     * @return void
     * @throws \UnexpectedValueException When path, identity, permissions, locking or writing fails.
     */
    protected function write(LogRecord $record): void
    {
        try {
            $this->openVerified();
            if (!$this->operations->lock($this->stream, true)) {
                throw new \UnexpectedValueException('The private log file could not be locked.');
            }
            try {
                $bytes = (string) $record->formatted;
                while ($bytes !== '') {
                    $this->verifyDescriptor();
                    $written = $this->operations->append($this->stream, $bytes);
                    if ($written === false || $written <= 0 || $written > strlen($bytes)) {
                        throw new \UnexpectedValueException('Writing the private log file failed.');
                    }
                    $bytes = substr($bytes, $written);
                }
            } finally {
                $this->operations->lock($this->stream, false);
            }
        } catch (\Throwable $exception) {
            $this->close();
            throw $exception;
        }
    }

    /** @return void Closes the owned descriptor; later records revalidate before reopening. */
    public function close(): void
    {
        if ($this->stream !== null) {
            $stream = $this->stream;
            $this->stream = null;
            $this->operations->close($stream);
        }
        $this->path = null;
    }

    /** @return void Resets processors and closes the file for long-running consumers. */
    public function reset(): void
    {
        parent::reset();
        $this->close();
    }

    /**
     * Opens only after destination preflight; identity changes require a validated reopen.
     *
     * @return void
     * @throws \UnexpectedValueException When the parent, target or effective mode is unsafe.
     */
    private function openVerified(): void
    {
        $path = $this->operations->canonicalize($this->filename);
        $parent = $this->operations->parentMetadata($path);
        if ($parent === false || ($parent['mode'] & 0170000) !== 0040000 || ($parent['mode'] & 0022) !== 0) {
            throw new \UnexpectedValueException('The private log parent must be a non-shared writable directory.');
        }
        $target = $this->operations->metadata($path);
        if ($target !== false) {
            $this->verifyRegular($target);
        }
        if ($this->stream !== null) {
            $descriptor = $this->operations->descriptorMetadata($this->stream);
            if (
                $path !== $this->path || $target === false || $descriptor === false
                || !$this->sameIdentity($target, $descriptor)
            ) {
                $this->close();
            }
        }
        if ($this->stream === null) {
            $stream = $this->operations->open($path, $target === false);
            if ($stream === false) {
                throw new \UnexpectedValueException('The private log file could not be opened.');
            }
            $this->stream = $stream;
            $this->path = $path;
            if (!$this->operations->restrict($path)) {
                throw new \UnexpectedValueException('The private log file could not be restricted to mode 0600.');
            }
        }
        $this->verifyDescriptor();
    }

    /**
     * Checks actual descriptor permissions and its current non-symlink path identity.
     *
     * @return void
     * @throws \UnexpectedValueException When effective mode or path identity is unsafe.
     */
    private function verifyDescriptor(): void
    {
        $descriptor = $this->operations->descriptorMetadata($this->stream);
        $target = $this->operations->metadata($this->path);
        if ($descriptor === false || $target === false) {
            throw new \UnexpectedValueException('The private log file metadata could not be verified.');
        }
        $this->verifyRegular($descriptor);
        $this->verifyRegular($target);
        if (
            ($descriptor['mode'] & 07777) !== 0600 || ($target['mode'] & 07777) !== 0600
            || !$this->sameIdentity($target, $descriptor)
        ) {
            throw new \UnexpectedValueException(
                'The private log descriptor must match its path and have effective mode 0600.',
            );
        }
    }

    /**
     * Rejects devices, directories, symlinks and multiply linked files.
     *
     * @param array $metadata Native stat fields.
     * @return void
     * @throws \UnexpectedValueException When the object is not a single-link regular file.
     */
    private function verifyRegular(array $metadata): void
    {
        if (($metadata['mode'] & 0170000) !== 0100000 || $metadata['nlink'] !== 1) {
            throw new \UnexpectedValueException('The private log destination must be a regular single-link file.');
        }
    }

    /** @param array $left Path stat. @param array $right Descriptor stat. @return bool Whether dev/inode match. */
    private function sameIdentity(array $left, array $right): bool
    {
        return $left['dev'] === $right['dev'] && $left['ino'] === $right['ino'];
    }
}
