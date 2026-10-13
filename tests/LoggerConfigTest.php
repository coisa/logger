<?php

declare(strict_types=1);

namespace CoiSA\Logger;

use CoiSA\Logger\Enum\LogLevel;
use FastForward\Config\ConfigInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit coverage of validated immutable configuration.
 *
 * @coversDefaultClass \CoiSA\Logger\LoggerConfig
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

    /** Verifies opt-in file-only defaults cannot fall back to console output. */
    public function testFileOnlyWithoutDestinationDisablesLogging(): void
    {
        $config = $this->configuration(['file_only' => true]);
        self::assertFalse($config->enabled);
        self::assertSame('', $config->filename);
        self::assertSame([], $config->processors);
    }

    /** Verifies a zero filename is not mistaken for a disabled destination. */
    public function testZeroFilenameRemainsEnabled(): void
    {
        $config = $this->configuration(['file_only' => true, 'filename' => '0']);
        self::assertTrue($config->enabled);
        self::assertSame('0', $config->filename);
    }

    /**
     * Verifies null or blank file-only destinations cannot enable a stdout fallback.
     *
     * @dataProvider disabledDestinations
     * @param mixed $filename The optional configured destination.
     */
    public function testOptionalFileDestinations(mixed $filename): void
    {
        $config = $this->configuration(['file_only' => true, 'filename' => $filename, 'enabled' => true]);
        self::assertFalse($config->enabled);
        self::assertSame('', $config->filename);
    }

    /** @return list<array{mixed}> Values representing absent destinations. */
    public static function disabledDestinations(): array
    {
        return [[null], [''], ['  ']];
    }

    /** Verifies readonly typed configuration exposes the selected finite values. */
    public function testProcessorAndFormatterSelectionsAreTyped(): void
    {
        $config = $this->configuration(['processors' => ['psr_message'], 'formatter' => 'json']);
        self::assertSame([\CoiSA\Logger\Enum\Processor::PSR_MESSAGE], $config->processors);
        self::assertSame(\CoiSA\Logger\Enum\Formatter::JSON, $config->formatter);
    }

    /** Verifies legacy defaults and explicit processor opt-out remain distinct. */
    public function testNewOptionsPreserveLegacyDefaults(): void
    {
        $config = $this->configuration([]);
        self::assertTrue($config->enabled);
        self::assertFalse($config->fileOnly);
        self::assertNull($config->processors);
        self::assertSame([], $this->configuration(['processors' => []])->processors);
    }

    /**
     * Verifies opt-in settings fail closed before filesystem access.
     *
     * @dataProvider invalidNewOptions
     * @param array<string, mixed> $options Invalid values.
     */
    public function testInvalidNewSettings(array $options): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->configuration($options);
    }

    /** @return list<array{array<string, mixed>}> Invalid opt-in values. */
    public static function invalidNewOptions(): array
    {
        return [
            [['enabled' => 'false']], [['file_only' => 1]],
            [['file_only' => true, 'filename' => 'php://stdout']],
            [['file_only' => true, 'filename' => 'file:///tmp/debug']],
            [['processors' => ['Unknown\\Processor']]], [['processors' => 'psr_message']],
            [['processors' => ['psr_message', 'psr_message']]], [['processors' => [false]]],
            [['processors' => [0 => 'psr_message', 2 => 'psr_message']]],
            [['formatter' => 'xml']],
        ];
    }

    /**
     * Verifies canonical strings against independently specified enum cases.
     *
     * @param string $value The configured level name.
     * @param LogLevel $expected The expected enum case.
     *
     * @covers ::__construct
     * @dataProvider canonicalLevels
     */
    public function testCanonicalLevel(string $value, LogLevel $expected): void
    {
        self::assertSame($expected, $this->configuration(['level' => $value])->level);
    }

    /**
     * Provides every canonical PSR-3 level.
     *
     * @return list<array{string, LogLevel}> Names and expected cases.
     */
    public static function canonicalLevels(): array
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
     * @param mixed $options The invalid section or settings.
     * @param string $field The expected diagnostic field.
     *
     * @covers ::__construct
     * @dataProvider invalidOptions
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
     * @return list<array{mixed, string}> Invalid settings and diagnostics.
     */
    public static function invalidOptions(): array
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
     * @param mixed $options The section supplied by the configuration mock.
     *
     * @return LoggerConfig The normalized value under test.
     */
    private function configuration(mixed $options): LoggerConfig
    {
        $source = $this->createMock(ConfigInterface::class);
        $source->expects(self::once())->method('get')->with('logger', [])->willReturn($options);

        return new LoggerConfig($source);
    }
}
