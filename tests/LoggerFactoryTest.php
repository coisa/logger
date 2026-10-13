<?php

declare(strict_types=1);

namespace CoiSA\Logger;

use FastForward\Config\ArrayConfig;
use FastForward\Config\ConfigInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

final class LoggerFactoryTest extends TestCase
{
    private string $filename;

    protected function setUp(): void
    {
        $filename = tempnam(sys_get_temp_dir(), 'coisa-logger-test-');
        self::assertIsString($filename);
        $this->filename = $filename;
    }

    protected function tearDown(): void
    {
        unlink($this->filename);
    }

    public function testFactoryRemainsUsableWithoutConfig(): void
    {
        self::assertInstanceOf(LoggerInterface::class, (new LoggerFactory())($this->container()));
    }

    public function testAbsentAndEmptyConfigPreserveStdoutDebugDefaults(): void
    {
        foreach ([false, true] as $configured) {
            $script = 'require "vendor/autoload.php"; '
                . '$container = new class implements \\Psr\\Container\\ContainerInterface {'
                . ' public function has(string $id): bool { return '
                . ($configured ? '$id === \\FastForward\\Config\\ConfigInterface::class' : 'false') . '; }'
                . ' public function get(string $id): mixed { return new \\FastForward\\Config\\ArrayConfig([]); }'
                . '}; (new \\CoiSA\\Logger\\LoggerFactory())($container)->debug("default-stream-marker");';
            $process = proc_open([PHP_BINARY, '-r', $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            self::assertIsResource($process);
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0, proc_close($process));
            self::assertStringContainsString('default-stream-marker', $stdout);
            self::assertSame('', $stderr);
        }
    }

    public function testInvalidContainerTimezoneFailsClearly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('container timezone');
        (new LoggerFactory())($this->container([\DateTimeZone::class => new \stdClass()]));
    }

    public function testUppercaseLevelAndUnrelatedConfigAreAccepted(): void
    {
        $logger = (new LoggerFactory())($this->container([ConfigInterface::class => new ArrayConfig([
            'application' => ['name' => 'synthetic'],
            'logger' => ['level' => 'ERROR', 'filename' => $this->filename],
        ])]));
        $logger->warning('hidden uppercase');
        $logger->error('visible uppercase');
        self::assertStringNotContainsString('hidden uppercase', file_get_contents($this->filename));
        self::assertStringContainsString('visible uppercase', file_get_contents($this->filename));
    }

    public function testPartialConfigUsesDebugLevelAndContainerTimezone(): void
    {
        $logger = $this->configured(['filename' => $this->filename], new \DateTimeZone('Pacific/Kiritimati'));
        $logger->debug('partial {value}', ['value' => 'configured']);
        $output = file_get_contents($this->filename);
        self::assertStringContainsString('partial configured', $output);
        self::assertStringContainsString('+14:00', $output);
    }

    public function testConfigControlsFileLevelAndOverridesContainerTimezone(): void
    {
        $logger = $this->configured([
            'filename' => $this->filename,
            'level' => 'warning',
            'timezone' => 'Pacific/Kiritimati',
        ], new \DateTimeZone('UTC'));
        $logger->debug('filtered marker');
        $logger->warning('visible marker');
        $output = file_get_contents($this->filename);
        self::assertStringNotContainsString('filtered marker', $output);
        self::assertStringContainsString('visible marker', $output);
        self::assertStringContainsString('+14:00', $output);
    }

    public function testTimezoneObjectIsAccepted(): void
    {
        $logger = $this->configured([
            'filename' => $this->filename,
            'timezone' => new \DateTimeZone('Pacific/Kiritimati'),
        ]);
        $logger->info('timezone object');
        self::assertStringContainsString('+14:00', file_get_contents($this->filename));
    }

    public function testExplicitNullTimezoneDoesNotResolveContainerTimezone(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(static fn (string $id): bool => $id === ConfigInterface::class);
        $container->expects(self::once())->method('get')->with(ConfigInterface::class)->willReturn(new ArrayConfig([
            'logger' => ['filename' => $this->filename, 'timezone' => null],
        ]));
        self::assertInstanceOf(LoggerInterface::class, (new LoggerFactory())($container));
    }

    /** @dataProvider invalidConfiguration */
    public function testInvalidConfigurationFailsClearly(mixed $options, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        (new LoggerFactory())($this->container([ConfigInterface::class => new ArrayConfig(['logger' => $options])]));
    }

    public static function invalidConfiguration(): array
    {
        return [
            'section' => ['invalid', 'logger must be an array'],
            'level type' => [['level' => 100], 'logger.level'],
            'unknown level' => [['level' => 'verbose'], 'logger.level'],
            'empty level' => [['level' => ''], 'logger.level'],
            'null level' => [['level' => null], 'logger.level'],
            'filename type' => [['filename' => false], 'logger.filename'],
            'empty filename' => [['filename' => ''], 'logger.filename'],
            'blank filename' => [['filename' => '  '], 'logger.filename'],
            'NUL filename' => [['filename' => "bad\0path"], 'logger.filename'],
            'timezone type' => [['timezone' => 42], 'logger.timezone'],
            'unknown timezone' => [['timezone' => 'Not/AZone'], 'logger.timezone'],
        ];
    }

    public function testInvalidConfigServiceFailsClearly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(ConfigInterface::class);
        (new LoggerFactory())($this->container([ConfigInterface::class => new \stdClass()]));
    }

    public function testProvidersResolveConfiguredLoggerThroughExistingAliases(): void
    {
        $config = new ArrayConfig(['logger' => ['filename' => $this->filename, 'level' => 'error']]);
        $provider = new ContainerInteropServiceProvider();
        $factories = $provider->getFactories();
        $logger = $factories[Logger::class]($this->container([ConfigInterface::class => $config]));
        $container = $this->container([Logger::class => $logger, LoggerInterface::class => $logger]);
        self::assertSame($logger, $factories[LoggerInterface::class]($container));
        self::assertSame($logger, $factories['logger']($container));
        self::assertSame([], $provider->getExtensions());

        $dependencies = (new ConfigProvider())()['dependencies'];
        $factory = new ($dependencies['factories'][Logger::class])();
        $laminasLogger = $factory($this->container([ConfigInterface::class => $config]));
        $laminasLogger->info('filtered provider');
        $laminasLogger->error('visible provider');
        self::assertStringNotContainsString('filtered provider', file_get_contents($this->filename));
        self::assertStringContainsString('visible provider', file_get_contents($this->filename));
        self::assertSame(LoggerInterface::class, $dependencies['aliases']['logger']);
        self::assertSame(Logger::class, $dependencies['aliases'][LoggerInterface::class]);
    }

    private function configured(array $options, ?\DateTimeZone $timezone = null): LoggerInterface
    {
        $services = [ConfigInterface::class => new ArrayConfig(['logger' => $options])];
        if ($timezone !== null) {
            $services[\DateTimeZone::class] = $timezone;
        }

        return (new LoggerFactory())($this->container($services));
    }

    private function container(array $services = []): ContainerInterface
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnCallback(static fn (string $id): bool => array_key_exists($id, $services));
        $container->method('get')->willReturnCallback(static fn (string $id): mixed => $services[$id]);

        return $container;
    }
}
