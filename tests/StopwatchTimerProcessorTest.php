<?php

declare(strict_types=1);

namespace CoiSA\Logger;

use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

final class StopwatchTimerProcessorTest extends TestCase
{
    public function testTimerStartsLapsAndStopsWithInstalledStopwatch(): void
    {
        $processor = new StopwatchTimerProcessor();
        foreach (['start', 'start', 'stop'] as $action) {
            $record = $processor(new LogRecord(
                datetime: new \DateTimeImmutable(),
                channel: 'compatibility',
                level: Level::Debug,
                message: 'timer',
                context: ['timer' => ['task' => $action]],
            ));
            self::assertNotEmpty($record->extra['timer']);
            self::assertIsString($record->extra['timer'][0]);
        }
    }

    public function testRecordWithoutTimerRemainsUnchanged(): void
    {
        $record = new LogRecord(new \DateTimeImmutable(), 'compatibility', Level::Debug, 'ordinary');
        self::assertSame($record, (new StopwatchTimerProcessor())($record));
    }

    public function testStoppingUnknownTimerFails(): void
    {
        $this->expectException(\LogicException::class);
        (new StopwatchTimerProcessor())(new LogRecord(
            new \DateTimeImmutable(),
            'compatibility',
            Level::Debug,
            'timer',
            ['timer' => ['unknown' => 'stop']],
        ));
    }
}
