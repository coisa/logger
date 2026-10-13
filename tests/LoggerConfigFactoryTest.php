<?php

declare(strict_types=1);

namespace CoiSA\Logger;

use CoiSA\Logger\Enum\LogLevel;
use FastForward\Config\ConfigInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * Unit coverage of configuration service creation with mocked dependencies.
 *
 * @coversDefaultClass \CoiSA\Logger\LoggerConfigFactory
 */
final class LoggerConfigFactoryTest extends TestCase
{
    /**
     * Verifies an absent source retains the defaults without fetching a service.
     *
     * @covers ::__invoke
     */
    public function testMissingSourceUsesDefaults(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())->method('has')->with(ConfigInterface::class)->willReturn(false);
        $container->expects(self::never())->method('get');
        $config = (new LoggerConfigFactory())($container);
        self::assertSame(LogLevel::DEBUG, $config->level);
        self::assertSame(LoggerConfig::DEFAULT_FILENAME, $config->filename);
        self::assertNull($config->timezone);
    }

    /**
     * Verifies configured nullable defaults through the registered source mock.
     *
     * @covers ::__invoke
     */
    public function testConfiguredSourceIsConsumed(): void
    {
        $source = $this->createMock(ConfigInterface::class);
        $source->expects(self::once())->method('get')->with('logger', [])->willReturn([
            'level' => 'warning', 'filename' => null, 'timezone' => null,
        ]);
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())->method('has')->with(ConfigInterface::class)->willReturn(true);
        $container->expects(self::once())->method('get')->with(ConfigInterface::class)->willReturn($source);
        $config = (new LoggerConfigFactory())($container);
        self::assertSame(LogLevel::WARNING, $config->level);
        self::assertSame(LoggerConfig::DEFAULT_FILENAME, $config->filename);
        self::assertNull($config->timezone);
    }

    /**
     * Verifies invalid registered service types are not treated as missing sources.
     *
     * @covers ::__invoke
     */
    public function testInvalidSourceServiceFailsClosed(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->with(ConfigInterface::class)->willReturn(true);
        $container->method('get')->with(ConfigInterface::class)->willReturn(null);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(ConfigInterface::class);
        (new LoggerConfigFactory())($container);
    }

    /**
     * Verifies validation errors from the supplied source are propagated.
     *
     * @covers ::__invoke
     */
    public function testInvalidSettingsArePropagated(): void
    {
        $source = $this->createMock(ConfigInterface::class);
        $source->method('get')->willReturn(['level' => 'ERROR']);
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($source);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('logger.level');
        (new LoggerConfigFactory())($container);
    }
}
