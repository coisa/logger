<?php

declare(strict_types=1);

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

namespace CoiSA\Logger;

use CoiSA\Logger\Enum\Formatter;
use CoiSA\Logger\Enum\LogLevel as ConfiguredLogLevel;
use CoiSA\Logger\Enum\Processor;
use CoiSA\Logger\Support\CustomProcessor;
use CoiSA\Logger\Support\MemoryLogStream;
use CoiSA\Logger\Support\RequiredConstructorProcessor;
use FastForward\Config\ConfigInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Unit tests of container composition and static creation with an in-memory stream spy.
 *
 * @coversDefaultClass \CoiSA\Logger\LoggerFactory
 *
 * @internal
 */
final class LoggerFactoryTest extends TestCase
{
    private const DESTINATION = 'coisalogunit://records';

    /** @var bool Whether this test owns the registered spy protocol. */
    private bool $registered = false;

    /**
     * Installs an isolated memory stream instead of real console or file IO.
     */
    protected function setUp(): void
    {
        MemoryLogStream::$records   = '';
        CustomProcessor::$instances = 0;
        CustomProcessor::$calls     = 0;
        $this->registered           = stream_wrapper_register('coisalogunit', MemoryLogStream::class);
        self::assertTrue($this->registered);
    }

    /**
     * Removes only the protocol installed by the current test.
     */
    protected function tearDown(): void
    {
        if ($this->registered) {
            stream_wrapper_unregister('coisalogunit');
        }

        MemoryLogStream::$records   = '';
        CustomProcessor::$instances = 0;
        CustomProcessor::$calls     = 0;
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
            ->willReturn($this->configuration([]))
        ;
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
     * @param \DateTimeZone|string $timezone the explicit configured timezone
     *
     * @covers ::__invoke
     *
     * @dataProvider provideConfiguredTimezoneSkipsContainerFallbackCases
     */
    public function testConfiguredTimezoneSkipsContainerFallback(\DateTimeZone|string $timezone): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())->method('get')->with(LoggerConfig::class)
            ->willReturn($this->configuration(['timezone' => $timezone]))
        ;
        $container->expects(self::never())->method('has');
        self::assertInstanceOf(LoggerInterface::class, (new LoggerFactory())($container));
    }

    /**
     * Supplies explicit timezone values.
     *
     * @return list<array{\DateTimeZone|string}> supported values
     */
    public static function provideConfiguredTimezoneSkipsContainerFallbackCases(): iterable
    {
        return [['UTC'], [new \DateTimeZone('UTC')]];
    }

    /**
     * Verifies that both null and absent configuration allow nullable legacy service values.
     *
     * @param mixed                   $fallback a supported container timezone value
     * @param array<array-key, mixed> $options  the logger settings
     *
     * @covers ::__invoke
     *
     * @dataProvider provideLazyContainerTimezoneFallbackCases
     */
    public function testLazyContainerTimezoneFallback(mixed $fallback, array $options): void
    {
        $config    = $this->configuration($options + ['filename' => self::DESTINATION]);
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())->method('has')->with(\DateTimeZone::class)->willReturn(true);
        $container->expects(self::exactly(2))->method('get')->willReturnMap([
            [LoggerConfig::class, $config],
            [\DateTimeZone::class, $fallback],
        ]);
        (new LoggerFactory())($container)->debug('fallback marker');
        self::assertStringContainsString('fallback marker', MemoryLogStream::$records);
        if (null !== $fallback) {
            self::assertStringContainsString('+14:00', MemoryLogStream::$records);
        }
    }

    /**
     * Provides missing/null settings with accepted service values.
     *
     * @return list<array{mixed, array<array-key, mixed>}> fallback and settings
     */
    public static function provideLazyContainerTimezoneFallbackCases(): iterable
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
     * @param mixed $fallback the invalid timezone value
     *
     * @covers ::__invoke
     *
     * @dataProvider provideInvalidContainerTimezoneCases
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
     * @return list<array{mixed}> values requiring rejection
     */
    public static function provideInvalidContainerTimezoneCases(): iterable
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
        $enum   = LoggerFactory::createLogger(ConfiguredLogLevel::ERROR, self::DESTINATION);
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
    public function testDisabledLoggerPreservesConcreteTypeWithoutOutput(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->with(LoggerConfig::class)->willReturn(
            $this->configuration([
                'enabled' => false, 'filename' => self::DESTINATION, 'processors' => [CustomProcessor::class],
            ]),
        );
        $container->expects(self::never())->method('has');
        $logger = (new LoggerFactory())($container);
        self::assertInstanceOf(Logger::class, $logger);
        $logger->withLevel('debug')->emergency('must not be written');
        self::assertSame('', MemoryLogStream::$records);
        self::assertSame(0, CustomProcessor::$instances);
        self::assertSame(0, CustomProcessor::$calls);
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
                'filename'  => self::DESTINATION, 'processors' => [Processor::PSR_MESSAGE],
                'formatter' => 'json', 'timezone' => 'UTC',
            ]),
        );
        (new LoggerFactory())($container)->info('unit {job}', ['job' => 'synthetic']);
        $record = json_decode(MemoryLogStream::$records, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('unit synthetic', $record['message']);
        self::assertSame([], $record['extra']);
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

    /** Verifies the shared static API accepts explicit presentation and processor choices. */
    public function testStaticCreationSupportsSelectedProcessorsAndFormatter(): void
    {
        $logger = LoggerFactory::createLogger(
            ConfiguredLogLevel::INFO,
            self::DESTINATION,
            new \DateTimeZone('UTC'),
            [Processor::PSR_MESSAGE],
            Formatter::JSON,
        );
        $logger->debug('hidden');
        $logger->info('unit {job}', ['job' => 'synthetic']);
        $record = json_decode(MemoryLogStream::$records, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('unit synthetic', $record['message']);
        self::assertSame([], $record['extra']);
    }

    /** Verifies custom classes are accepted by their processor contract, not enum membership. */
    public function testConfiguredCustomProcessorEnrichesRecords(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->with(LoggerConfig::class)->willReturn($this->configuration([
            'filename'  => self::DESTINATION, 'processors' => [CustomProcessor::class],
            'formatter' => 'json', 'timezone' => 'UTC',
        ]));
        (new LoggerFactory())($container)->info('synthetic record');
        $record = json_decode(MemoryLogStream::$records, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['custom' => 'synthetic'], $record['extra']);
        self::assertSame(1, CustomProcessor::$instances);
        self::assertSame(1, CustomProcessor::$calls);
    }

    /**
     * Verifies both factory APIs reuse a constructed processor without cloning or rebuilding it.
     *
     * @dataProvider provideProcessorInstanceEnrichesRecordsCases
     *
     * @param bool $configured whether to resolve the registered configuration
     */
    public function testProcessorInstanceEnrichesRecords(bool $configured): void
    {
        $processor = new RequiredConstructorProcessor('instance marker');
        if ($configured) {
            $container = $this->createMock(ContainerInterface::class);
            $container->method('get')->with(LoggerConfig::class)->willReturn($this->configuration([
                'filename'  => self::DESTINATION, 'processors' => [$processor],
                'formatter' => 'json', 'timezone' => 'UTC',
            ]));
            $logger = (new LoggerFactory())($container);
        } else {
            $logger = LoggerFactory::createLogger(
                filename: self::DESTINATION,
                processors: [$processor],
                formatter: Formatter::JSON,
            );
        }

        $logger->info('synthetic instance record');
        $record = json_decode(MemoryLogStream::$records, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['configured' => 'instance marker'], $record['extra']);
        self::assertSame(1, $processor->calls);
    }

    /** @return list<array{bool}> Container and static creation paths. */
    public static function provideProcessorInstanceEnrichesRecordsCases(): iterable
    {
        return [[true], [false]];
    }

    /** Verifies disabled logging never invokes a supplied processor instance. */
    public function testDisabledLoggerDoesNotInvokeProcessorInstance(): void
    {
        $processor = new RequiredConstructorProcessor('disabled');
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->with(LoggerConfig::class)->willReturn($this->configuration([
            'enabled' => false, 'filename' => self::DESTINATION, 'processors' => [$processor],
        ]));
        (new LoggerFactory())($container)->emergency('must not be written');
        self::assertSame(0, $processor->calls);
        self::assertSame('', MemoryLogStream::$records);
    }

    /**
     * Creates a validated value using a mocked source, not a concrete config backend.
     *
     * @param array<array-key, mixed> $options the source settings
     *
     * @return LoggerConfig the immutable value returned by the mocked container
     */
    private function configuration(array $options): LoggerConfig
    {
        $source = $this->createMock(ConfigInterface::class);
        $source->expects(self::once())->method('get')->with('logger', [])->willReturn($options);

        return new LoggerConfig($source);
    }
}
