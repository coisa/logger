<?php

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

declare(strict_types=1);

namespace CoiSA\Logger;

use CoiSA\Logger\Enum\LogLevel;
use FastForward\Config\ConfigInterface;

/**
 * Immutable snapshot of normalized logger configuration.
 *
 * Missing or null settings SHALL use defaults. Invalid non-null settings MUST
 * fail during construction. A null timezone allows the factory to resolve its
 * container fallback; this object MUST NOT depend on a service container.
 */
final readonly class LoggerConfig
{
    /** @var non-empty-string The default destination for logger records. */
    public const DEFAULT_FILENAME = 'php://stdout';

    /**
     * @var LogLevel The validated canonical minimum logging level.
     */
    public LogLevel $level;

    /**
     * @var non-empty-string The validated output path or stream.
     */
    public string $filename;

    /**
     * @var \DateTimeZone|null The configured timezone, or null to allow a fallback.
     */
    public ?\DateTimeZone $timezone;

    /**
     * Constructs a validated snapshot from the optional logger section.
     *
     * @param ConfigInterface $config Configuration containing level, filename and timezone settings.
     *
     * @throws \InvalidArgumentException When the section or a non-null setting is invalid.
     */
    public function __construct(ConfigInterface $config)
    {
        $options = self::resolveOptions($config);
        $this->level = self::resolveLevel($options['level'] ?? LogLevel::DEBUG->value);
        $this->filename = self::resolveFilename($options['filename'] ?? self::DEFAULT_FILENAME);
        $this->timezone = self::resolveTimezone($options['timezone'] ?? null);
    }

    /**
     * Resolves a nested logger section without retaining mutable configuration.
     *
     * @param ConfigInterface $config The application-owned configuration source.
     *
     * @return array<array-key, mixed> The logger settings before individual validation.
     *
     * @throws \InvalidArgumentException When the logger section is not an array or configuration object.
     */
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

    /**
     * Validates a canonical PSR-3 level name and resolves its backed enum.
     *
     * @param mixed $level The non-null level setting after applying the default.
     *
     * @return LogLevel The validated minimum logging level.
     *
     * @throws \InvalidArgumentException When the value is not a canonical lowercase level string.
     */
    private static function resolveLevel(mixed $level): LogLevel
    {
        if (!is_string($level)) {
            throw new \InvalidArgumentException('logger.level must be a PSR-3 level name.');
        }

        return LogLevel::tryFrom($level)
            ?? throw new \InvalidArgumentException('logger.level must be a PSR-3 level name.');
    }

    /**
     * Validates the configured file path or stream without opening it.
     *
     * @param mixed $filename The non-null destination after applying the default.
     *
     * @return non-empty-string The validated destination.
     *
     * @throws \InvalidArgumentException When the value is not a non-empty string without NUL bytes.
     */
    private static function resolveFilename(mixed $filename): string
    {
        if (!is_string($filename) || trim($filename) === '' || str_contains($filename, "\0")) {
            throw new \InvalidArgumentException(
                'logger.filename must be a non-empty path or stream without NUL bytes.',
            );
        }

        return $filename;
    }

    /**
     * Normalizes the timezone setting while preserving null as the fallback signal.
     *
     * @param mixed $timezone A timezone name, DateTimeZone instance, or null.
     *
     * @return \DateTimeZone|null The normalized timezone, or null for a fallback.
     *
     * @throws \InvalidArgumentException When a non-null timezone value is invalid.
     */
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
