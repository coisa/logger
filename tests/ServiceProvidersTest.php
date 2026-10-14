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

use FastForward\Config\ConfigInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Unit coverage of public provider registrations without running a concrete container.
 *
 * @covers \CoiSA\Logger\ConfigProvider
 * @covers \CoiSA\Logger\ContainerInteropServiceProvider
 *
 * @internal
 */
final class ServiceProvidersTest extends TestCase
{
    /**
     * Verifies factory entries and logger aliases through mocked dependencies.
     *
     * @covers \CoiSA\Logger\ContainerInteropServiceProvider::getExtensions
     * @covers \CoiSA\Logger\ContainerInteropServiceProvider::getFactories
     */
    public function testInteropProviderRegistrationsAndAliases(): void
    {
        $provider  = new ContainerInteropServiceProvider();
        $factories = $provider->getFactories();
        self::assertInstanceOf(LoggerConfigFactory::class, $factories[LoggerConfig::class]);
        self::assertInstanceOf(LoggerFactory::class, $factories[Logger::class]);
        $logger    = $this->createMock(LoggerInterface::class);
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

    /**
     * Executes provider registrations through a mocked shared PSR container.
     *
     * @param string $provider the registration adapter
     * @param bool   $enabled  whether the PSR logger is enabled
     *
     * @dataProvider provideBindingsPreserveConcreteTypesAndEnabledIdentityCases
     */
    public function testBindingsPreserveConcreteTypesAndEnabledIdentity(string $provider, bool $enabled): void
    {
        $dependencies = 'interop' === $provider
            ? ['factories' => (new ContainerInteropServiceProvider())->getFactories(), 'aliases' => []]
            : (new ConfigProvider())()['dependencies'];
        $source = $this->createMock(ConfigInterface::class);
        $source->method('get')->with('logger', [])->willReturn([
            'enabled' => $enabled, 'filename' => 'php://memory', 'processors' => [], 'timezone' => 'UTC',
        ]);
        $container = $this->createMock(ContainerInterface::class);
        $entries   = [ConfigInterface::class => $source];
        $resolve   = null;
        $resolve   = static function (string $id) use (&$resolve, &$entries, $container, $dependencies): object {
            if (isset($entries[$id])) {
                return $entries[$id];
            }
            if (isset($dependencies['aliases'][$id])) {
                return $resolve($dependencies['aliases'][$id]);
            }
            $factory = $dependencies['factories'][$id];
            if (\is_string($factory)) {
                $factory = new $factory();
            }

            return $entries[$id] = $factory($container);
        };
        $container->method('get')->willReturnCallback($resolve);
        $container->method('has')->willReturnCallback(
            static fn (string $id): bool => isset($entries[$id]) || isset($dependencies['factories'][$id]),
        );
        $psrLogger = $container->get(LoggerInterface::class);
        $concrete  = $container->get(Logger::class);
        self::assertSame($psrLogger, $container->get('logger'));
        self::assertInstanceOf(Logger::class, $concrete);
        self::assertSame($psrLogger, $concrete);
        if (!$enabled) {
            $concrete->withLevel('debug')->emergency('disabled synthetic record');
        }
    }

    /** @return list<array{string, bool}> Both portable adapters in enabled and disabled modes. */
    public static function provideBindingsPreserveConcreteTypesAndEnabledIdentityCases(): iterable
    {
        return [['interop', true], ['interop', false], ['laminas', true], ['laminas', false]];
    }
}
