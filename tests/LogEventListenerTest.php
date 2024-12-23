<?php

declare(strict_types=1);

namespace CoiSA\Logger;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Log\LoggerInterface;

/**
 * @coversDefaultClass \CoiSA\Logger\LogEventListener
 */
final class LogEventListenerTest extends TestCase
{
    use ProphecyTrait;

    private ObjectProphecy $logger;
    private LogEventListener $listener;

    /**
     * Initializes common dependencies for tests.
     */
    protected function setUp(): void
    {
        $this->logger = $this->prophesize(LoggerInterface::class);
        $this->listener = new LogEventListener($this->logger->reveal());
    }

    /**
     * @covers ::__invoke
     * @dataProvider provideEventsForLogging
     */
    public function testInvokeLogsEventCorrectlyWithVariousInputs(
        object $event,
        string $expectedName,
        string $template = LogEventListener::DEFAULT_MESSAGE
    ): void {
        $this->listener = new LogEventListener($this->logger->reveal(), $template);

        $this->logger->info(
            $template,
            ['name' => $expectedName, 'event' => $event]
        )->shouldBeCalled();

        $this->listener->__invoke($event);
    }

    /**
     * @covers ::__construct
     */
    public function testConstructorUsesDefaultLoggerWhenNoneProvided(): void
    {
        $listener = new LogEventListener();
        $this->assertInstanceOf(LogEventListener::class, $listener);
    }

    /**
     * Provides different event scenarios for testing.
     *
     * @return array<string, array>
     */
    public static function provideEventsForLogging(): array
    {
        $eventWithName = new class {
            public function getName(): string
            {
                return 'TestEvent';
            }
        };

        $eventWithoutName = new class {
        };

        $customTemplate = uniqid('Custom log message: {name}');

        return [
            'event with getName method' => [
                $eventWithName,
                'TestEvent',
                LogEventListener::DEFAULT_MESSAGE,
            ],
            'event without getName method' => [
                $eventWithoutName,
                get_class($eventWithoutName),
                LogEventListener::DEFAULT_MESSAGE,
            ],
            'event with custom template' => [
                $eventWithName,
                'TestEvent',
                $customTemplate,
            ],
        ];
    }
}
