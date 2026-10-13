<?php

declare(strict_types=1);

namespace CoiSA\Logger;

use CoiSA\Logger\Enum\LogLevel as ConfiguredLogLevel;
use CoiSA\Logger\Handler\FileOperations;
use CoiSA\Logger\Support\MemoryLogStream;
use FastForward\Config\ConfigInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Unit tests of container composition and static creation with an in-memory stream spy.
 *
 * @coversDefaultClass \CoiSA\Logger\LoggerFactory
 */
final class LoggerFactoryTest extends TestCase
{
    private const DESTINATION = 'coisalogunit://records';

    /** @var bool Whether this test owns the registered spy protocol. */
    private bool $registered = false;

    /**
     * Installs an isolated memory stream instead of real console or file IO.
     *
     * @return void
     */
    protected function setUp(): void
    {
        MemoryLogStream::$records = '';
        $this->registered = stream_wrapper_register('coisalogunit', MemoryLogStream::class);
        self::assertTrue($this->registered);
    }

    /**
     * Removes only the protocol installed by the current test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if ($this->registered) {
            stream_wrapper_unregister('coisalogunit');
        }
        MemoryLogStream::$records = '';
    }

    /**
     * Verifies consumption of the registered value without constructing another service.
     *
     * @covers ::__invoke
     */
    public function testConsumesRegisteredConfiguration(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())->method('get')->with(LoggerConfig::class)
            ->willReturn($this->configuration([]));
        $container->expects(self::once())->method('has')->with(\DateTimeZone::class)->willReturn(false);
        self::assertInstanceOf(LoggerInterface::class, (new LoggerFactory())($container));
    }

    /**
     * Verifies that an incorrectly registered configuration fails closed.
     *
     * @covers ::__invoke
     */
    public function testInvalidConfigurationService(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->with(LoggerConfig::class)->willReturn(null);
        $container->expects(self::never())->method('has');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('LoggerConfig');
        (new LoggerFactory())($container);
    }

    /**
     * Verifies that non-null configuration skips all fallback-service resolution.
     *
     * @param string|\DateTimeZone $timezone The explicit configured timezone.
     *
     * @covers ::__invoke
     * @dataProvider configuredTimezones
     */
    public function testConfiguredTimezoneSkipsContainerFallback(string|\DateTimeZone $timezone): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())->method('get')->with(LoggerConfig::class)
            ->willReturn($this->configuration(['timezone' => $timezone]));
        $container->expects(self::never())->method('has');
        self::assertInstanceOf(LoggerInterface::class, (new LoggerFactory())($container));
    }

    /**
     * Supplies explicit timezone values.
     *
     * @return list<array{string|\DateTimeZone}> Supported values.
     */
    public static function configuredTimezones(): array
    {
        return [['UTC'], [new \DateTimeZone('UTC')]];
    }

    /**
     * Verifies that both null and absent configuration allow nullable legacy service values.
     *
     * @param mixed $fallback A supported container timezone value.
     * @param array<array-key, mixed> $options The logger settings.
     *
     * @covers ::__invoke
     * @dataProvider timezoneFallbacks
     */
    public function testLazyContainerTimezoneFallback(mixed $fallback, array $options): void
    {
        $config = $this->configuration($options + ['filename' => self::DESTINATION]);
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())->method('has')->with(\DateTimeZone::class)->willReturn(true);
        $container->expects(self::exactly(2))->method('get')->willReturnMap([
            [LoggerConfig::class, $config],
            [\DateTimeZone::class, $fallback],
        ]);
        (new LoggerFactory())($container)->debug('fallback marker');
        self::assertStringContainsString('fallback marker', MemoryLogStream::$records);
        if ($fallback !== null) {
            self::assertStringContainsString('+14:00', MemoryLogStream::$records);
        }
    }

    /**
     * Provides missing/null settings with accepted service values.
     *
     * @return list<array{mixed, array<array-key, mixed>}> Fallback and settings.
     */
    public static function timezoneFallbacks(): array
    {
        return [
            [null, []], [null, ['timezone' => null]],
            ['Pacific/Kiritimati', []], ['Pacific/Kiritimati', ['timezone' => null]],
            [new \DateTimeZone('Pacific/Kiritimati'), []],
            [new \DateTimeZone('Pacific/Kiritimati'), ['timezone' => null]],
        ];
    }

    /**
     * Verifies malformed non-null service values produce clear diagnostics.
     *
     * @param mixed $fallback The invalid timezone value.
     *
     * @covers ::__invoke
     * @dataProvider invalidFallbacks
     */
    public function testInvalidContainerTimezone(mixed $fallback): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->with(\DateTimeZone::class)->willReturn(true);
        $container->method('get')->willReturnMap([
            [LoggerConfig::class, $this->configuration([])],
            [\DateTimeZone::class, $fallback],
        ]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('logger.timezone');
        (new LoggerFactory())($container);
    }

    /**
     * Supplies invalid container values.
     *
     * @return list<array{mixed}> Values requiring rejection.
     */
    public static function invalidFallbacks(): array
    {
        return [[false], ['Not/AZone'], ["UTC\0bad"]];
    }

    /**
     * Verifies enum normalization and backward-compatible uppercase string levels.
     *
     * @covers ::createLogger
     */
    public function testStaticEnumAndLegacyStringHaveEquivalentThresholds(): void
    {
        $enum = LoggerFactory::createLogger(ConfiguredLogLevel::ERROR, self::DESTINATION);
        $string = LoggerFactory::createLogger('ERROR', self::DESTINATION);
        $enum->warning('hidden enum');
        $string->warning('hidden string');
        $enum->error('visible enum');
        $string->error('visible string');
        self::assertStringContainsString('visible enum', MemoryLogStream::$records);
        self::assertStringContainsString('visible string', MemoryLogStream::$records);
        self::assertStringNotContainsString('hidden enum', MemoryLogStream::$records);
        self::assertStringNotContainsString('hidden string', MemoryLogStream::$records);
    }

    /**
     * Verifies the default creation API remains usable without opening its lazy destination.
     *
     * @covers ::createLogger
     */
    public function testStaticDefaultsRemainAvailable(): void
    {
        self::assertInstanceOf(LoggerInterface::class, LoggerFactory::createLogger());
    }

    /** Verifies disabled logging skips timezone resolution and cannot emit records. */
    public function testDisabledLoggerPreservesConcreteServiceWithoutOutput(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->with(LoggerConfig::class)->willReturn(
            $this->configuration(['enabled' => false, 'filename' => self::DESTINATION]),
        );
        $container->expects(self::never())->method('has');
        $logger = (new LoggerFactory())($container);
        self::assertInstanceOf(Logger::class, $logger);
        $logger->emergency('must not be written');
        self::assertSame('', MemoryLogStream::$records);
    }

    /**
     * Verifies disabled backend state is independent of the process timezone and fallback service.
     *
     * @dataProvider disabledConfigurations
     * @param array<string, mixed> $options The disabled logger settings.
     */
    public function testDisabledBackendUsesFixedUtc(array $options): void
    {
        $previous = date_default_timezone_get();
        try {
            date_default_timezone_set('Pacific/Auckland');
            $container = $this->createMock(ContainerInterface::class);
            $container->method('get')->with(LoggerConfig::class)->willReturn($this->configuration($options));
            $container->expects(self::never())->method('has');
            $logger = (new LoggerFactory())($container);
            $backend = (new \ReflectionProperty(Logger::class, 'logger'))->getValue($logger);
            self::assertSame('UTC', $backend->getTimezone()->getName());
            self::assertSame([], $backend->getProcessors());
        } finally {
            date_default_timezone_set($previous);
        }
    }

    /** @return list<array{array<string, mixed>}> Explicit and implicit opt-outs. */
    public static function disabledConfigurations(): array
    {
        return [[['enabled' => false]], [['file_only' => true]], [['file_only' => true, 'filename' => ' ']]];
    }

    /** Verifies the selected formatter and processor opt-out in a memory-only sink. */
    public function testJsonWithNoProcessorsDoesNotAddMetadata(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->with(LoggerConfig::class)->willReturn(
            $this->configuration([
                'filename' => self::DESTINATION, 'processors' => [], 'formatter' => 'json', 'timezone' => 'UTC',
            ]),
        );
        $logger = (new LoggerFactory())($container);
        $logger->info('unit {job}', ['job' => 'synthetic']);
        $record = json_decode(MemoryLogStream::$records, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('unit {job}', $record['message']);
        self::assertSame(['job' => 'synthetic'], $record['context']);
        self::assertSame([], $record['extra']);
        self::assertArrayNotHasKey('trace', $record);
    }

    /** Verifies explicit interpolation can be selected without unrelated metadata processors. */
    public function testSelectedProcessorInterpolatesOnlyRequestedContext(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->with(LoggerConfig::class)->willReturn(
            $this->configuration([
                'filename' => self::DESTINATION, 'processors' => ['psr_message'],
                'formatter' => 'json', 'timezone' => 'UTC',
            ]),
        );
        (new LoggerFactory())($container)->info('unit {job}', ['job' => 'synthetic']);
        $record = json_decode(MemoryLogStream::$records, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('unit synthetic', $record['message']);
        self::assertSame([], $record['extra']);
    }

    /** Verifies file-only configuration uses the secure boundary and metadata-free constant channel. */
    public function testFileOnlyWiringWritesJsonThroughPrivateHandler(): void
    {
        $operations = $this->createMock(FileOperations::class);
        $metadata = ['mode' => 0100600, 'nlink' => 1, 'dev' => 1, 'ino' => 2];
        $operations->method('canonicalize')->with('0')->willReturn('/owned/0');
        $operations->method('parentMetadata')->willReturn(['mode' => 0040700]);
        $operations->method('metadata')->willReturn($metadata);
        $operations->method('descriptorMetadata')->willReturn($metadata);
        $operations->method('open')->willReturn(new \stdClass());
        $operations->method('restrict')->willReturn(true);
        $operations->method('lock')->willReturn(true);
        $bytes = '';
        $operations->expects(self::once())->method('append')->willReturnCallback(
            static function ($stream, string $data) use (&$bytes): int {
                $bytes .= $data;
                return strlen($data);
            },
        );
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->with(LoggerConfig::class)->willReturn(
            $this->configuration(['file_only' => true, 'filename' => '0', 'formatter' => 'json', 'timezone' => 'UTC']),
        );
        (new LoggerFactory($operations))($container)->info('synthetic file record');
        $record = json_decode($bytes, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('application', $record['channel']);
        self::assertSame('synthetic file record', $record['message']);
        self::assertSame([], $record['extra']);
        self::assertSame('', MemoryLogStream::$records);
    }

    /** Verifies an optional destination missing from file-only settings creates no sink and resolves no fallback. */
    public function testMissingFileOnlyDestinationHasNoSideEffects(): void
    {
        $operations = $this->createMock(FileOperations::class);
        $operations->expects(self::never())->method('canonicalize');
        $operations->expects(self::never())->method('open');
        $operations->expects(self::never())->method('append');
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->with(LoggerConfig::class)->willReturn($this->configuration(['file_only' => true]));
        $container->expects(self::never())->method('has');
        (new LoggerFactory($operations))($container)->emergency('disabled optional file');
        self::assertSame('', MemoryLogStream::$records);
    }

    /**
     * Verifies defaults for nullable level values through the configured logger.
     *
     * @covers ::__invoke
     */
    public function testNullLevelUsesDebugDefault(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->with(LoggerConfig::class)->willReturn(
            $this->configuration(['level' => null, 'filename' => self::DESTINATION]),
        );
        $container->method('has')->with(\DateTimeZone::class)->willReturn(false);
        (new LoggerFactory())($container)->debug('default debug marker');
        self::assertStringContainsString('default debug marker', MemoryLogStream::$records);
    }

    /**
     * Creates a validated value using a mocked source, not a concrete config backend.
     *
     * @param array<array-key, mixed> $options The source settings.
     *
     * @return LoggerConfig The immutable value returned by the mocked container.
     */
    private function configuration(array $options): LoggerConfig
    {
        $source = $this->createMock(ConfigInterface::class);
        $source->expects(self::once())->method('get')->with('logger', [])->willReturn($options);

        return new LoggerConfig($source);
    }
}
