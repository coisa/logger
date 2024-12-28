<?php

declare(strict_types=1);

namespace CoiSA\Logger;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Class StopwatchTimerProcessor
 *
 * A Monolog Processor using Symfony's Stopwatch component with sections
 * for structured time tracking.
 *
 * This processor SHALL measure execution time grouped into logical sections,
 * and include details such as elapsed time, total accumulated time,
 * and execution count in the log record.
 */
final class StopwatchTimerProcessor implements ProcessorInterface
{
    /**
     * @var Stopwatch Symfony Stopwatch instance for precise time tracking.
     */
    private Stopwatch $stopwatch;

    /**
     * Constructor.
     *
     * @param ?Stopwatch $stopwatch The Symfony Stopwatch instance for precise time tracking.
     */
    public function __construct(?Stopwatch $stopwatch = null)
    {
        $this->stopwatch = $stopwatch ?? new Stopwatch(true);
    }

    /**
     * Processes a log record to include timer data.
     *
     * Handles 'start' and 'stop' commands from 'context.timer' and tracks
     * execution time using Stopwatch.
     *
     * @param LogRecord $record The log record.
     *
     * @return LogRecord The updated log record with timer information.
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        if (!isset($record['context']['timer']) || !is_array($record['context']['timer'])) {
            return $record;
        }

        foreach ($record['context']['timer'] as $timer => $action) {
            if (!in_array($action, ['start', 'stop'], true)) {
                throw new \InvalidArgumentException("Invalid timer action: $action");
            }

            if ($action === 'start') {
                $this->handleStart($timer);
                continue;
            }

            $this->handleStop($timer);
        }

        foreach ($this->stopwatch->getRootSectionEvents() as $event) {
            if (!$event->isStarted() && !isset($record['context']['timer'][$event->getName()])) {
                continue;
            }

            $record['extra']['timer'][] = (string) $event;
        }

        return $record;
    }

    /**
     * Handles starting a timer.
     *
     * @param string $timer Timer name.
     */
    private function handleStart(string $timer): void
    {
        if ($this->stopwatch->isStarted($timer)) {
            $this->stopwatch->lap($timer);
            return;
        }

        $this->stopwatch->start($timer);
    }

    /**
     * Handles stopping a timer.
     *
     * @param string $timer Timer name.
     */
    private function handleStop(string $timer): void
    {
        if (!$this->stopwatch->isStarted($timer)) {
            throw new \LogicException("Cannot stop timer '$timer' because it was not started.");
        }

        $this->stopwatch->stop($timer);
    }
}
