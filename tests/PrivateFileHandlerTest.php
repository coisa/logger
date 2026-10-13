<?php

declare(strict_types=1);

namespace CoiSA\Logger;

use CoiSA\Logger\Handler\FileOperations;
use CoiSA\Logger\Handler\PrivateFileHandler;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

/** Unit tests of file policy with mocked system calls and synthetic metadata. */
final class PrivateFileHandlerTest extends TestCase
{
    /** Verifies creation, permission enforcement and formatted append without disk IO. */
    public function testCreatesPrivateFileBeforeWriting(): void
    {
        $operations = $this->operations();
        $stream = new \stdClass();
        $calls = 0;
        $operations->method('metadata')->with('/owned/debug.log')->willReturnCallback(
            function () use (&$calls): array|false {
                return $calls++ === 0 ? false : $this->metadata();
            },
        );
        $operations->expects(self::once())->method('open')->with('/owned/debug.log', true)->willReturn($stream);
        $operations->expects(self::once())->method('restrict')->with('/owned/debug.log')->willReturn(true);
        $operations->method('descriptorMetadata')->with($stream)->willReturn($this->metadata());
        $written = '';
        $operations->expects(self::once())->method('append')->willReturnCallback(
            static function ($stream, string $bytes) use (&$written): int {
                $written .= $bytes;
                return strlen($bytes);
            },
        );
        (new PrivateFileHandler('debug.log', operations: $operations))->handle($this->record());
        self::assertStringContainsString('synthetic marker', $written);
    }

    /**
     * Verifies invalid destinations fail before open or write.
     *
     * @dataProvider invalidTargets
     * @param array<string, int> $metadata Synthetic target metadata.
     */
    public function testRejectsNonRegularOrLinkedTargets(array $metadata): void
    {
        $operations = $this->operations();
        $operations->method('metadata')->willReturn($metadata);
        $operations->expects(self::never())->method('open');
        $operations->expects(self::never())->method('append');
        $this->expectException(\UnexpectedValueException::class);
        (new PrivateFileHandler('debug.log', operations: $operations))->handle($this->record());
    }

    /** @return list<array{array<string, int>}> Device, directory, symlink and hardlink targets. */
    public static function invalidTargets(): array
    {
        return [
            [['mode' => 0020600, 'nlink' => 1, 'dev' => 1, 'ino' => 2]],
            [['mode' => 0040700, 'nlink' => 1, 'dev' => 1, 'ino' => 2]],
            [['mode' => 0120777, 'nlink' => 1, 'dev' => 1, 'ino' => 2]],
            [['mode' => 0100600, 'nlink' => 2, 'dev' => 1, 'ino' => 2]],
        ];
    }

    /** Verifies chmod success alone cannot permit a publicly readable descriptor. */
    public function testEffectivePermissionsAreVerified(): void
    {
        $operations = $this->operations();
        $operations->method('metadata')->willReturn($this->metadata());
        $operations->method('open')->willReturn(new \stdClass());
        $operations->method('restrict')->willReturn(true);
        $operations->method('descriptorMetadata')->willReturn($this->metadata(mode: 0100644));
        $operations->expects(self::never())->method('append');
        $operations->expects(self::once())->method('close');
        $this->expectException(\UnexpectedValueException::class);
        (new PrivateFileHandler('debug.log', operations: $operations))->handle($this->record());
    }

    /** Verifies a failed chmod cannot be ignored even if metadata happens to look private. */
    public function testPermissionFailureStopsWriting(): void
    {
        $operations = $this->operations();
        $operations->method('metadata')->willReturn($this->metadata());
        $operations->method('open')->willReturn(new \stdClass());
        $operations->method('restrict')->willReturn(false);
        $operations->expects(self::never())->method('append');
        $this->expectException(\UnexpectedValueException::class);
        (new PrivateFileHandler('debug.log', operations: $operations))->handle($this->record());
    }

    /** Verifies effective permissions are checked again for each subsequent record. */
    public function testChangedPermissionsRejectNextRecord(): void
    {
        $operations = $this->operations();
        $operations->method('metadata')->willReturn($this->metadata());
        $operations->method('open')->willReturn(new \stdClass());
        $operations->method('restrict')->willReturn(true);
        $private = true;
        $operations->method('descriptorMetadata')->willReturnCallback(
            function () use (&$private): array {
                return $this->metadata(mode: $private ? 0100600 : 0100644);
            },
        );
        $operations->expects(self::once())->method('append')->willReturnCallback(
            static fn ($stream, string $bytes): int => strlen($bytes),
        );
        $handler = new PrivateFileHandler('debug.log', operations: $operations);
        $handler->handle($this->record());
        $private = false;
        $this->expectException(\UnexpectedValueException::class);
        $handler->handle($this->record());
    }

    /** Verifies an identity mismatch never writes through the wrong descriptor. */
    public function testDescriptorIdentityMismatchFailsClosed(): void
    {
        $operations = $this->operations();
        $operations->method('metadata')->willReturn($this->metadata());
        $operations->method('open')->willReturn(new \stdClass());
        $operations->method('restrict')->willReturn(true);
        $operations->method('descriptorMetadata')->willReturn($this->metadata(inode: 9));
        $operations->expects(self::never())->method('append');
        $this->expectException(\UnexpectedValueException::class);
        (new PrivateFileHandler('debug.log', operations: $operations))->handle($this->record());
    }

    /** Verifies each partial append is guarded, and no busy-loop occurs on zero-byte writes. */
    public function testPartialWritesRevalidateAndComplete(): void
    {
        $operations = $this->operations();
        $operations->method('metadata')->willReturn($this->metadata());
        $operations->method('open')->willReturn(new \stdClass());
        $operations->method('restrict')->willReturn(true);
        $operations->expects(self::exactly(3))->method('descriptorMetadata')->willReturn($this->metadata());
        $bytes = '';
        $operations->expects(self::exactly(2))->method('append')->willReturnCallback(
            static function ($stream, string $data) use (&$bytes): int {
                $chunk = $bytes === '' ? substr($data, 0, 5) : $data;
                $bytes .= $chunk;
                return strlen($chunk);
            },
        );
        (new PrivateFileHandler('debug.log', operations: $operations))->handle($this->record());
        self::assertStringContainsString('synthetic marker', $bytes);
    }

    /** Verifies safe replacements reopen and check chmod/descriptor again, instead of writing the old inode. */
    public function testReopensChangedFileWithFreshValidation(): void
    {
        $operations = $this->operations();
        $inode = 2;
        $operations->method('metadata')->willReturnCallback(
            function () use (&$inode): array {
                return $this->metadata(inode: $inode);
            },
        );
        $first = new \stdClass();
        $second = new \stdClass();
        $operations->expects(self::exactly(2))->method('open')->willReturnOnConsecutiveCalls($first, $second);
        $operations->expects(self::exactly(2))->method('restrict')->willReturn(true);
        $operations->method('descriptorMetadata')->willReturnCallback(
            fn ($stream): array => $this->metadata(inode: $stream === $first ? 2 : 9),
        );
        $operations->expects(self::exactly(2))->method('append')->willReturnCallback(
            static fn ($stream, string $bytes): int => strlen($bytes),
        );
        $handler = new PrivateFileHandler('debug.log', operations: $operations);
        $handler->handle($this->record());
        $inode = 9;
        $handler->handle($this->record());
        $handler->close();
    }

    /**
     * Verifies unsafe parent modes fail before destination access.
     *
     * @dataProvider unsafeParents
     * @param array|false $parent Invalid native parent metadata.
     */
    public function testUnsafeParentFailsBeforeOpen(array|false $parent): void
    {
        $operations = $this->createMock(FileOperations::class);
        $operations->method('canonicalize')->willReturn('/owned/debug.log');
        $operations->method('parentMetadata')->willReturn($parent);
        $operations->expects(self::never())->method('metadata');
        $operations->expects(self::never())->method('open');
        $this->expectException(\UnexpectedValueException::class);
        (new PrivateFileHandler('debug.log', operations: $operations))->handle($this->record());
    }

    /** @return list<array{array|false}> Missing, non-directory and shared parents. */
    public static function unsafeParents(): array
    {
        return [[false], [['mode' => 0100600]], [['mode' => 0040777]], [['mode' => 0040770]]];
    }

    /**
     * Verifies IO failures do not write or retry indefinitely.
     *
     * @dataProvider ioFailures
     * @param string $method The failing boundary operation.
     * @param mixed $result Its failure result.
     */
    public function testIoFailureFailsClosed(string $method, mixed $result): void
    {
        $operations = $this->operations(lockResult: $method !== 'lock');
        $operations->method('metadata')->willReturn($this->metadata());
        $operations->method('descriptorMetadata')->willReturn($this->metadata());
        $operations->method('restrict')->willReturn(true);
        $operations->method('open')->willReturn($method === 'open' ? $result : new \stdClass());
        if ($method === 'append') {
            $operations->expects(self::once())->method('append')->willReturn($result);
        } else {
            $operations->expects(self::never())->method('append');
        }
        $this->expectException(\UnexpectedValueException::class);
        (new PrivateFileHandler('debug.log', operations: $operations))->handle($this->record());
    }

    /** @return list<array{string, mixed}> Open, lock and append failures. */
    public static function ioFailures(): array
    {
        return [['open', false], ['lock', false], ['append', false], ['append', 0]];
    }

    /**
     * Verifies failed release closes the descriptor and preserves an earlier write error.
     *
     * @dataProvider failedReleases
     * @param bool $writeFails Whether the append itself fails first.
     * @param string $message The first observable failure diagnostic.
     */
    public function testFailedReleaseFailsClosed(bool $writeFails, string $message): void
    {
        $operations = $this->operations(lockResult: null);
        $stream = new \stdClass();
        $operations->method('metadata')->willReturn($this->metadata());
        $operations->method('descriptorMetadata')->willReturn($this->metadata());
        $operations->method('open')->willReturn($stream);
        $operations->method('restrict')->willReturn(true);
        $operations->expects(self::exactly(2))->method('lock')->willReturnOnConsecutiveCalls(true, false);
        $operations->expects(self::once())->method('append')->willReturnCallback(
            static fn ($handle, string $bytes): int|false => $writeFails ? false : strlen($bytes),
        );
        $operations->expects(self::once())->method('close')->with($stream);
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage($message);
        (new PrivateFileHandler('debug.log', operations: $operations))->handle($this->record());
    }

    /** @return list<array{bool, string}> Release-only failure and combined write/release failure. */
    public static function failedReleases(): array
    {
        return [[false, 'lock could not be released'], [true, 'Writing the private log file failed']];
    }

    /** Verifies local colon filenames remain usable without opening a native file. */
    public function testAcceptsLocalColonFilename(): void
    {
        $operations = $this->createMock(FileOperations::class);
        $operations->expects(self::never())->method('open');
        self::assertInstanceOf(PrivateFileHandler::class, new PrivateFileHandler('app:debug.log', operations: $operations));
    }

    /**
     * Verifies actual PHP wrapper syntax is rejected before any native operation.
     *
     * @dataProvider streamDestinations
     * @param string $filename The stream wrapper URL.
     */
    public function testRejectsStreamDestinations(string $filename): void
    {
        $operations = $this->createMock(FileOperations::class);
        $operations->expects(self::never())->method('canonicalize');
        $operations->expects(self::never())->method('open');
        $this->expectException(\InvalidArgumentException::class);
        new PrivateFileHandler($filename, operations: $operations);
    }

    /** @return list<array{string}> Built-in and valid custom wrapper syntax. */
    public static function streamDestinations(): array
    {
        return [['php://stdout'], ['file:///tmp/debug'], ['data:text/plain,record'], ['1x://records']];
    }

    /** @return FileOperations Mocked native boundary with a trusted synthetic parent. */
    private function operations(?bool $lockResult = true): FileOperations
    {
        $operations = $this->createMock(FileOperations::class);
        $operations->method('canonicalize')->willReturn('/owned/debug.log');
        $operations->method('parentMetadata')->willReturn(['mode' => 0040700]);
        if ($lockResult !== null) {
            $operations->method('lock')->willReturn($lockResult);
        }
        return $operations;
    }

    /** @return array<string, int> Complete fields consumed by private-file policy. */
    private function metadata(int $mode = 0100600, int $inode = 2): array
    {
        return ['mode' => $mode, 'nlink' => 1, 'dev' => 1, 'ino' => $inode];
    }

    /** @return LogRecord Fixed synthetic value, independent of the real clock. */
    private function record(): LogRecord
    {
        return new LogRecord(new \DateTimeImmutable('2026-01-01T00:00:00Z'), 'unit', Level::Debug, 'synthetic marker');
    }
}
