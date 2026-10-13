<?php

declare(strict_types=1);

namespace CoiSA\Logger;

use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Stopwatch\StopwatchEvent;

/**
 * Unit coverage of timer orchestration with a mocked Stopwatch dependency.
 *
 * @coversDefaultClass \CoiSA\Logger\StopwatchTimerProcessor
 */
final class StopwatchTimerProcessorTest extends TestCase
{
    /**
     * Verifies start, lap and stop dispatch without running a real timer.
     *
     * @covers ::__invoke
     */
    public function testTimerStartsLapsAndStops(): void
    {
        $watch = $this->createMock(Stopwatch::class);
        $watch->method('isStarted')->with('task')->willReturnOnConsecutiveCalls(false, true, true);
        $watch->expects(self::once())->method('start')->with('task');
        $watch->expects(self::once())->method('lap')->with('task');
        $watch->expects(self::once())->method('stop')->with('task');
        $watch->method('getRootSectionEvents')->willReturn([]);
        $processor = new StopwatchTimerProcessor($watch);
        foreach (['start', 'start', 'stop'] as $action) {
            $record = new LogRecord(
                new \DateTimeImmutable('2026-01-01T00:00:00Z'),
                'unit',
                Level::Debug,
                'timer',
                ['timer' => ['task' => $action]],
            );
            self::assertSame($record, $processor($record));
        }
    }

    /**
     * Verifies records without timer context do not access the dependency.
     *
     * @covers ::__invoke
     */
    public function testRecordWithoutTimerRemainsUnchanged(): void
    {
        $watch = $this->createMock(Stopwatch::class);
        $watch->expects(self::never())->method('isStarted');
        $watch->expects(self::never())->method('getRootSectionEvents');
        $record = new LogRecord(new \DateTimeImmutable('2026-01-01T00:00:00Z'), 'unit', Level::Debug, 'ordinary');
        self::assertSame($record, (new StopwatchTimerProcessor($watch))($record));
    }

    /**
     * Verifies timer metadata includes the event's formatted value without running a clock.
     *
     * @covers ::__invoke
     */
    public function testFormatsTimerMetadataFromRootEvent(): void
    {
        $event = $this->createMock(StopwatchEvent::class);
        $event->method('isStarted')->willReturn(true);
        $event->expects(self::once())->method('__toString')->willReturn('unit timer metadata');
        $watch = $this->createMock(Stopwatch::class);
        $watch->method('isStarted')->with('task')->willReturn(false);
        $watch->expects(self::once())->method('start')->with('task')->willReturn($event);
        $watch->method('getRootSectionEvents')->willReturn(['task' => $event]);
        $record = new LogRecord(
            new \DateTimeImmutable('2026-01-01T00:00:00Z'),
            'unit',
            Level::Debug,
            'timer',
            ['timer' => ['task' => 'start']],
        );
        $result = (new StopwatchTimerProcessor($watch))($record);
        self::assertSame(['unit timer metadata'], $result->extra['timer']);
    }

    /**
     * Verifies an unknown timer is rejected before invoking stop.
     *
     * @covers ::__invoke
     */
    public function testStoppingUnknownTimerFails(): void
    {
        $watch = $this->createMock(Stopwatch::class);
        $watch->method('isStarted')->with('unknown')->willReturn(false);
        $watch->expects(self::never())->method('stop');
        $this->expectException(\LogicException::class);
        (new StopwatchTimerProcessor($watch))(new LogRecord(
            new \DateTimeImmutable('2026-01-01T00:00:00Z'),
            'unit',
            Level::Debug,
            'timer',
            ['timer' => ['unknown' => 'stop']],
        ));
    }
}
