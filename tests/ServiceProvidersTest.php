<?php

declare(strict_types=1);

namespace CoiSA\Logger;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Unit coverage of public provider registrations without running a concrete container.
 */
final class ServiceProvidersTest extends TestCase
{
    /**
     * Verifies factory entries and logger aliases through mocked dependencies.
     *
     * @covers \CoiSA\Logger\ContainerInteropServiceProvider::getFactories
     * @covers \CoiSA\Logger\ContainerInteropServiceProvider::getExtensions
     */
    public function testInteropProviderRegistrationsAndAliases(): void
    {
        $provider = new ContainerInteropServiceProvider();
        $factories = $provider->getFactories();
        self::assertInstanceOf(LoggerConfigFactory::class, $factories[LoggerConfig::class]);
        self::assertInstanceOf(LoggerFactory::class, $factories[Logger::class]);
        $logger = $this->createMock(LoggerInterface::class);
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::exactly(2))->method('get')->willReturnMap([
            [LoggerInterface::class, $logger], [Logger::class, $logger],
        ]);
        self::assertSame($logger, $factories['logger']($container));
        self::assertSame($logger, $factories[LoggerInterface::class]($container));
        self::assertSame([], $provider->getExtensions());
    }

    /**
     * Verifies the declarative configuration registry uses the dedicated config factory.
     *
     * @covers \CoiSA\Logger\ConfigProvider::__invoke
     * @covers \CoiSA\Logger\ConfigProvider::getDependencies
     */
    public function testConfigProviderRegistrations(): void
    {
        $dependencies = (new ConfigProvider())()['dependencies'];
        self::assertSame(LoggerConfigFactory::class, $dependencies['factories'][LoggerConfig::class]);
        self::assertSame(LoggerFactory::class, $dependencies['factories'][Logger::class]);
        self::assertSame(LoggerInterface::class, $dependencies['aliases']['logger']);
        self::assertSame(Logger::class, $dependencies['aliases'][LoggerInterface::class]);
    }
}
