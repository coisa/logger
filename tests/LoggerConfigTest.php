<?php

declare(strict_types=1);

namespace CoiSA\Logger;

use CoiSA\Logger\Enum\LogLevel;
use FastForward\Config\ArrayConfig;
use PHPUnit\Framework\TestCase;

final class LoggerConfigTest extends TestCase
{
    public function testDefaultsAreTypedAndTimezoneIsNotConfigured(): void
    {
        $config = new LoggerConfig(new ArrayConfig());

        self::assertSame(LogLevel::DEBUG, $config->getLevel());
        self::assertSame('php://stdout', $config->getFilename());
        self::assertFalse($config->hasTimezone());
        self::assertNull($config->getTimezone());
    }

    /** @dataProvider canonicalLevels */
    public function testCanonicalLevelIsReturnedAsEnum(string $level, LogLevel $expected): void
    {
        $config = new LoggerConfig(new ArrayConfig(['logger' => ['level' => $level]]));

        self::assertSame($expected, $config->getLevel());
        self::assertSame($level, $config->getLevel()->value);
    }

    public static function canonicalLevels(): array
    {
        return [
            ['debug', LogLevel::DEBUG],
            ['info', LogLevel::INFO],
            ['notice', LogLevel::NOTICE],
            ['warning', LogLevel::WARNING],
            ['error', LogLevel::ERROR],
            ['critical', LogLevel::CRITICAL],
            ['alert', LogLevel::ALERT],
            ['emergency', LogLevel::EMERGENCY],
        ];
    }

    public function testConfigurationIsASnapshotOfValidatedValues(): void
    {
        $source = new ArrayConfig(['logger' => [
            'level' => 'info',
            'filename' => 'php://stderr',
            'timezone' => 'UTC',
        ]]);
        $config = new LoggerConfig($source);

        $source->set('logger.level', 'error');
        $source->set('logger.filename', 'php://stdout');
        $source->remove('logger.timezone');

        self::assertSame(LogLevel::INFO, $config->getLevel());
        self::assertSame('php://stderr', $config->getFilename());
        self::assertTrue($config->hasTimezone());
        self::assertSame('UTC', $config->getTimezone()->getName());
    }

    public function testPartialConfigPreservesDefaultsAndAcceptsTimezoneFallback(): void
    {
        $config = new LoggerConfig(new ArrayConfig(['logger' => ['filename' => 'php://stderr']]));
        $fallback = new \DateTimeZone('Pacific/Kiritimati');

        self::assertSame('php://stderr', $config->getFilename());
        self::assertSame(LogLevel::DEBUG, $config->getLevel());
        self::assertSame($fallback, $config->getTimezone($fallback));
        self::assertSame('UTC', $config->getTimezone('UTC')->getName());
        self::assertFalse($config->hasTimezone());
    }

    public function testExplicitTimezoneNameOverridesInvalidFallback(): void
    {
        $config = new LoggerConfig(new ArrayConfig(['logger' => ['timezone' => 'UTC']]));

        self::assertTrue($config->hasTimezone());
        self::assertSame('UTC', $config->getTimezone(new \stdClass())->getName());
    }

    public function testExplicitTimezoneObjectOverridesFallback(): void
    {
        $timezone = new \DateTimeZone('UTC');
        $config = new LoggerConfig(new ArrayConfig(['logger' => ['timezone' => $timezone]]));

        self::assertTrue($config->hasTimezone());
        self::assertSame($timezone, $config->getTimezone('Not/AZone'));
    }

    public function testExplicitNullTimezoneIsDifferentFromAbsentAndIgnoresFallback(): void
    {
        $config = new LoggerConfig(new ArrayConfig(['logger' => ['timezone' => null]]));

        self::assertTrue($config->hasTimezone());
        self::assertNull($config->getTimezone(new \stdClass()));
    }

    /** @dataProvider invalidConfiguration */
    public function testInvalidConfigurationFailsDuringConstruction(mixed $options, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        new LoggerConfig(new ArrayConfig(['logger' => $options]));
    }

    public static function invalidConfiguration(): array
    {
        return [
            'section' => ['invalid', 'logger must be an array'],
            'level type' => [['level' => 100], 'logger.level'],
            'unknown level' => [['level' => 'verbose'], 'logger.level'],
            'uppercase level' => [['level' => 'ERROR'], 'logger.level'],
            'mixed-case level' => [['level' => 'Error'], 'logger.level'],
            'null level' => [['level' => null], 'logger.level'],
            'filename type' => [['filename' => false], 'logger.filename'],
            'empty filename' => [['filename' => ''], 'logger.filename'],
            'NUL filename' => [['filename' => "bad\0path"], 'logger.filename'],
            'timezone type' => [['timezone' => 42], 'logger.timezone'],
            'unknown timezone' => [['timezone' => 'Not/AZone'], 'logger.timezone'],
            'NUL timezone' => [['timezone' => "UTC\0invalid"], 'logger.timezone'],
        ];
    }

    public function testEnumObjectIsNotAcceptedAsConfigurationInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('logger.level');
        new LoggerConfig(new ArrayConfig(['logger' => ['level' => LogLevel::ERROR]]));
    }

    /** @dataProvider invalidFallbacks */
    public function testInvalidTimezoneFallbackFailsOnlyWhenNeeded(mixed $fallback): void
    {
        $config = new LoggerConfig(new ArrayConfig());
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('logger.timezone');
        $config->getTimezone($fallback);
    }

    public static function invalidFallbacks(): array
    {
        return [[42], ['Not/AZone'], ["UTC\0invalid"]];
    }
}
