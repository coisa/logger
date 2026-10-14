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
use CoiSA\Logger\Enum\LogLevel;
use CoiSA\Logger\Enum\Processor;
use CoiSA\Logger\Support\AbstractProcessor;
use CoiSA\Logger\Support\OptionalConstructorProcessor;
use CoiSA\Logger\Support\PrivateConstructorProcessor;
use CoiSA\Logger\Support\RequiredConstructorProcessor;
use FastForward\Config\ConfigInterface;
use Monolog\Processor\PsrLogMessageProcessor;
use PHPUnit\Framework\TestCase;

/**
 * Unit coverage of validated immutable configuration.
 *
 * @coversDefaultClass \CoiSA\Logger\LoggerConfig
 *
 * @internal
 */
final class LoggerConfigTest extends TestCase
{
    /**
     * Verifies typed defaults through public properties.
     *
     * @covers ::__construct
     */
    public function testDefaultsAreTyped(): void
    {
        $config = $this->configuration([]);
        self::assertSame(LogLevel::DEBUG, $config->level);
        self::assertSame('php://stdout', $config->filename);
        self::assertNull($config->timezone);
    }

    /**
     * Verifies that null settings use the same defaults as missing keys.
     *
     * @covers ::__construct
     */
    public function testNullSettingsUseDefaults(): void
    {
        $config = $this->configuration(['level' => null, 'filename' => null, 'timezone' => null]);
        self::assertSame(LogLevel::DEBUG, $config->level);
        self::assertSame(LoggerConfig::DEFAULT_FILENAME, $config->filename);
        self::assertNull($config->timezone);
    }

    /** Verifies a zero filename is not mistaken for a disabled destination. */
    public function testZeroFilenameRemainsEnabled(): void
    {
        $config = $this->configuration(['filename' => '0']);
        self::assertTrue($config->enabled);
        self::assertSame('0', $config->filename);
    }

    /**
     * Verifies NUL destinations fail during configuration construction.
     *
     * @dataProvider provideDestinationsRejectNulBytesCases
     *
     * @param string $filename the malformed configured destination
     */
    public function testDestinationsRejectNulBytes(string $filename): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('logger.filename');
        $this->configuration(['filename' => $filename]);
    }

    /** @return list<array{string}> Pure, whitespace-mixed and nonblank NUL destinations. */
    public static function provideDestinationsRejectNulBytesCases(): iterable
    {
        return [["\0"], [" \t\0\r\n\v "], ["bad\0path"]];
    }

    /** Verifies readonly typed configuration exposes the selected finite values. */
    public function testProcessorAndFormatterSelectionsAreTyped(): void
    {
        $config = $this->configuration([
            'processors' => [PsrLogMessageProcessor::class], 'formatter' => 'json',
        ]);
        self::assertSame([PsrLogMessageProcessor::class], $config->processors);
        self::assertSame(Formatter::JSON, $config->formatter);
    }

    /** Verifies legacy defaults and explicit processor opt-out remain distinct. */
    public function testNewOptionsPreserveLegacyDefaults(): void
    {
        $config = $this->configuration([]);
        self::assertTrue($config->enabled);
        self::assertNull($config->processors);
        self::assertSame([], $this->configuration(['processors' => []])->processors);
    }

    /**
     * Verifies opt-in settings fail closed before filesystem access.
     *
     * @dataProvider provideInvalidNewSettingsCases
     *
     * @param array<string, mixed> $options invalid values
     */
    public function testInvalidNewSettings(array $options): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->configuration($options);
    }

    /** @return list<array{array<string, mixed>}> Invalid opt-in values. */
    public static function provideInvalidNewSettingsCases(): iterable
    {
        return [
            [['enabled' => 'false']],
            [['processors' => ['Unknown\Processor']]], [['processors' => 'psr_message']],
            [['processors' => [Processor::PSR_MESSAGE, Processor::PSR_MESSAGE]]], [['processors' => [false]]],
            [['processors' => [0 => Processor::PSR_MESSAGE, 2 => Processor::PSR_MESSAGE]]],
            [['processors' => ['psr_message']]], [['processors' => [\stdClass::class]]],
            [['formatter' => 'xml']],
        ];
    }

    /** Verifies an ordinary local filename with a colon is not mistaken for a wrapper. */
    public function testAcceptsLocalColonFilename(): void
    {
        $config = $this->configuration(['filename' => 'app:debug.log']);
        self::assertTrue($config->enabled);
        self::assertSame('app:debug.log', $config->filename);
    }

    /** Verifies typed enum selections remain immutable normalized configuration values. */
    public function testAcceptsProcessorEnumCases(): void
    {
        self::assertSame(
            [PsrLogMessageProcessor::class],
            $this->configuration(['processors' => [Processor::PSR_MESSAGE]])->processors,
        );
    }

    /**
     * Verifies invalid constructors fail when the configuration is normalized.
     *
     * @dataProvider provideRejectsNonConstructibleProcessorsCases
     *
     * @param class-string $processor the invalid processor implementation
     */
    public function testRejectsNonConstructibleProcessors(string $processor): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('logger.processors classes must be instantiable without arguments.');
        $this->configuration(['processors' => [$processor]]);
    }

    /** @return list<array{class-string}> Abstract, argument-requiring and inaccessible constructors. */
    public static function provideRejectsNonConstructibleProcessorsCases(): iterable
    {
        return [[AbstractProcessor::class], [RequiredConstructorProcessor::class], [PrivateConstructorProcessor::class]];
    }

    /** Verifies optional constructor parameters do not restrict custom implementations. */
    public function testAcceptsProcessorWithOptionalConstructorArguments(): void
    {
        self::assertSame(
            [OptionalConstructorProcessor::class],
            $this->configuration(['processors' => [OptionalConstructorProcessor::class]])->processors,
        );
    }

    /** Verifies constructed processors retain their identities and may use required arguments. */
    public function testAcceptsDistinctProcessorInstancesOfTheSameClass(): void
    {
        $first  = new RequiredConstructorProcessor('first');
        $second = new RequiredConstructorProcessor('second');
        $config = $this->configuration(['processors' => [$first, $second]]);
        self::assertSame($first, $config->processors[0]);
        self::assertSame($second, $config->processors[1]);
    }

    /** Verifies the same processor instance cannot accidentally run twice. */
    public function testRejectsDuplicateProcessorInstances(): void
    {
        $processor = new RequiredConstructorProcessor('duplicate');
        $this->expectException(\InvalidArgumentException::class);
        $this->configuration(['processors' => [$processor, $processor]]);
    }

    /**
     * Verifies canonical strings against independently specified enum cases.
     *
     * @param string   $value    the configured level name
     * @param LogLevel $expected the expected enum case
     *
     * @covers ::__construct
     *
     * @dataProvider provideCanonicalLevelCases
     */
    public function testCanonicalLevel(string $value, LogLevel $expected): void
    {
        self::assertSame($expected, $this->configuration(['level' => $value])->level);
    }

    /**
     * Provides every canonical PSR-3 level.
     *
     * @return list<array{string, LogLevel}> names and expected cases
     */
    public static function provideCanonicalLevelCases(): iterable
    {
        return [
            ['debug', LogLevel::DEBUG], ['info', LogLevel::INFO],
            ['notice', LogLevel::NOTICE], ['warning', LogLevel::WARNING],
            ['error', LogLevel::ERROR], ['critical', LogLevel::CRITICAL],
            ['alert', LogLevel::ALERT], ['emergency', LogLevel::EMERGENCY],
        ];
    }

    /**
     * Verifies nested ConfigInterface section normalization with mocks.
     *
     * @covers ::__construct
     */
    public function testNestedConfigurationObject(): void
    {
        $nested = $this->createMock(ConfigInterface::class);
        $nested->expects(self::once())->method('toArray')->willReturn(['filename' => 'php://memory']);
        $source = $this->createMock(ConfigInterface::class);
        $source->expects(self::once())->method('get')->with('logger', [])->willReturn($nested);
        self::assertSame('php://memory', (new LoggerConfig($source))->filename);
    }

    /**
     * Verifies that changing the source response cannot mutate the snapshot.
     *
     * @covers ::__construct
     */
    public function testSnapshotDoesNotRetainMutableSource(): void
    {
        $values = ['level' => 'info', 'filename' => 'php://memory', 'timezone' => 'UTC'];
        $source = $this->createMock(ConfigInterface::class);
        $source->expects(self::once())->method('get')->with('logger', [])->willReturnCallback(
            static function () use (&$values): array {
                return $values;
            },
        );
        $config = new LoggerConfig($source);
        $values = ['level' => 'error', 'filename' => 'php://stderr'];
        self::assertSame(LogLevel::INFO, $config->level);
        self::assertSame('php://memory', $config->filename);
        self::assertSame('UTC', $config->timezone->getName());
    }

    /**
     * Verifies that a public property cannot be reassigned.
     *
     * @covers ::__construct
     */
    public function testPropertiesAreReadonly(): void
    {
        $config = $this->configuration([]);
        $this->expectException(\Error::class);
        $config->filename = 'php://memory';
    }

    /**
     * Verifies normalization of a named timezone and identity of a value object.
     *
     * @covers ::__construct
     */
    public function testTimezoneValues(): void
    {
        self::assertSame('UTC', $this->configuration(['timezone' => 'UTC'])->timezone->getName());
        $zone = new \DateTimeZone('UTC');
        self::assertSame($zone, $this->configuration(['timezone' => $zone])->timezone);
    }

    /**
     * Verifies invalid non-null settings fail during construction.
     *
     * @param mixed  $options the invalid section or settings
     * @param string $field   the expected diagnostic field
     *
     * @covers ::__construct
     *
     * @dataProvider provideInvalidSettingsCases
     */
    public function testInvalidSettings(mixed $options, string $field): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($field);
        $this->configuration($options);
    }

    /**
     * Supplies invalid types, aliases and malicious string values.
     *
     * @return list<array{mixed, string}> invalid settings and diagnostics
     */
    public static function provideInvalidSettingsCases(): iterable
    {
        return [
            ['not-an-array', 'logger must be an array'],
            [['level' => 10], 'logger.level'], [['level' => LogLevel::ERROR], 'logger.level'],
            [['level' => 'ERROR'], 'logger.level'], [['level' => 'Error'], 'logger.level'],
            [['level' => 'unknown'], 'logger.level'], [['level' => ''], 'logger.level'],
            [['filename' => false], 'logger.filename'], [['filename' => ''], 'logger.filename'],
            [['filename' => ' '], 'logger.filename'], [['filename' => "bad\0path"], 'logger.filename'],
            [['timezone' => false], 'logger.timezone'], [['timezone' => 'Not/AZone'], 'logger.timezone'],
            [['timezone' => "UTC\0bad"], 'logger.timezone'],
        ];
    }

    /**
     * Constructs the SUT with a mocked configuration dependency.
     *
     * @param mixed $options the section supplied by the configuration mock
     *
     * @return LoggerConfig the normalized value under test
     */
    private function configuration(mixed $options): LoggerConfig
    {
        $source = $this->createMock(ConfigInterface::class);
        $source->expects(self::once())->method('get')->with('logger', [])->willReturn($options);

        return new LoggerConfig($source);
    }
}
