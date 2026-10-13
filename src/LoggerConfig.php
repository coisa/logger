<?php

declare(strict_types=1);

namespace CoiSA\Logger;

use CoiSA\Logger\Enum\LogLevel;
use FastForward\Config\ConfigInterface;

final class LoggerConfig
{
    private readonly LogLevel $level;
    private readonly string $filename;
    private readonly ?\DateTimeZone $timezone;
    private readonly bool $hasTimezone;

    public function __construct(ConfigInterface $config)
    {
        $options = self::resolveOptions($config);
        $this->level = self::resolveLevel(
            array_key_exists('level', $options) ? $options['level'] : LogLevel::DEBUG->value,
        );
        $this->filename = self::resolveFilename(
            array_key_exists('filename', $options) ? $options['filename'] : 'php://stdout',
        );
        $this->hasTimezone = array_key_exists('timezone', $options);
        $this->timezone = self::resolveTimezone($this->hasTimezone ? $options['timezone'] : null);
    }

    public function getLevel(): LogLevel
    {
        return $this->level;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function hasTimezone(): bool
    {
        return $this->hasTimezone;
    }

    public function getTimezone(mixed $fallback = null): ?\DateTimeZone
    {
        // An explicit null selects the process default, not the container fallback.
        return $this->hasTimezone ? $this->timezone : self::resolveTimezone($fallback);
    }

    private static function resolveOptions(ConfigInterface $config): array
    {
        $options = $config->get('logger', []);
        if ($options instanceof ConfigInterface) {
            $options = $options->toArray();
        }
        if (!is_array($options)) {
            throw new \InvalidArgumentException('logger must be an array.');
        }

        return $options;
    }

    private static function resolveLevel(mixed $level): LogLevel
    {
        if (!is_string($level)) {
            throw new \InvalidArgumentException('logger.level must be a PSR-3 level name.');
        }

        return LogLevel::tryFrom($level)
            ?? throw new \InvalidArgumentException('logger.level must be a PSR-3 level name.');
    }

    private static function resolveFilename(mixed $filename): string
    {
        if (!is_string($filename) || trim($filename) === '' || str_contains($filename, "\0")) {
            throw new \InvalidArgumentException(
                'logger.filename must be a non-empty path or stream without NUL bytes.',
            );
        }

        return $filename;
    }

    private static function resolveTimezone(mixed $timezone): ?\DateTimeZone
    {
        if (is_string($timezone)) {
            try {
                $timezone = new \DateTimeZone($timezone);
            } catch (\Exception | \ValueError $exception) {
                throw new \InvalidArgumentException('logger.timezone must be a valid timezone name.', 0, $exception);
            }
        }
        if ($timezone !== null && !$timezone instanceof \DateTimeZone) {
            throw new \InvalidArgumentException(
                'logger.timezone or the container timezone must be a DateTimeZone, timezone name or null.',
            );
        }

        return $timezone;
    }
}
