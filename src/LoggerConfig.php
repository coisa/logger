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

use CoiSA\Logger\Enum\Formatter;
use CoiSA\Logger\Enum\LogLevel;
use CoiSA\Logger\Enum\Processor;
use FastForward\Config\ConfigInterface;
use Monolog\Processor\ProcessorInterface;

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
     * @var LogLevel the validated canonical minimum logging level
     */
    public LogLevel $level;

    /**
     * @var string the output path or stream
     */
    public string $filename;

    /**
     * @var null|\DateTimeZone the configured timezone, or null to allow a fallback
     */
    public ?\DateTimeZone $timezone;

    /** @var bool Whether records may reach any sink. */
    public bool $enabled;

    /** @var null|list<class-string<ProcessorInterface>> Selected classes, or null for legacy defaults. */
    public ?array $processors;

    /** @var Formatter The selected record representation. */
    public Formatter $formatter;

    /**
     * Constructs a validated snapshot from the optional logger section.
     *
     * @param ConfigInterface $config the optional logger section, including sink and processor options
     *
     * @throws \InvalidArgumentException when the section or a non-null setting is invalid
     */
    public function __construct(ConfigInterface $config)
    {
        $options          = self::resolveOptions($config);
        $this->level      = self::resolveLevel($options['level'] ?? LogLevel::DEBUG->value);
        $this->filename   = self::resolveFilename($options['filename'] ?? self::DEFAULT_FILENAME);
        $this->enabled    = self::resolveBoolean($options['enabled'] ?? true, 'enabled');
        $this->processors = self::resolveProcessors($options['processors'] ?? null);
        $this->formatter  = self::resolveFormatter($options['formatter'] ?? Formatter::TEXT->value);
        $this->timezone   = self::resolveTimezone($options['timezone'] ?? null);
    }

    /**
     * Resolves a nested logger section without retaining mutable configuration.
     *
     * @param ConfigInterface $config the application-owned configuration source
     *
     * @return array<array-key, mixed> the logger settings before individual validation
     *
     * @throws \InvalidArgumentException when the logger section is not an array or configuration object
     */
    private static function resolveOptions(ConfigInterface $config): array
    {
        $options = $config->get('logger', []);
        if ($options instanceof ConfigInterface) {
            $options = $options->toArray();
        }
        if (!\is_array($options)) {
            throw new \InvalidArgumentException('logger must be an array.');
        }

        return $options;
    }

    /**
     * Validates a canonical PSR-3 level name and resolves its backed enum.
     *
     * @param mixed $level the non-null level setting after applying the default
     *
     * @return LogLevel the validated minimum logging level
     *
     * @throws \InvalidArgumentException when the value is not a canonical lowercase level string
     */
    private static function resolveLevel(mixed $level): LogLevel
    {
        if (!\is_string($level)) {
            throw new \InvalidArgumentException('logger.level must be a PSR-3 level name.');
        }

        return LogLevel::tryFrom($level)
            ?? throw new \InvalidArgumentException('logger.level must be a PSR-3 level name.');
    }

    /**
     * Validates the configured file path or stream without opening it.
     *
     * @param mixed $filename the non-null destination after applying the default
     *
     * @return string the destination
     *
     * @throws \InvalidArgumentException when the destination type or contents are invalid
     */
    private static function resolveFilename(mixed $filename): string
    {
        if (
            !\is_string($filename) || str_contains($filename, "\0") || '' === mb_trim($filename)
        ) {
            throw new \InvalidArgumentException(
                'logger.filename must be a non-empty path or stream without NUL bytes.',
            );
        }

        return $filename;
    }

    /**
     * Validates an explicit switch without coercing strings.
     *
     * @param mixed  $value the normalized switch
     * @param string $key   the diagnostic setting name
     *
     * @return bool the validated switch
     *
     * @throws \InvalidArgumentException when a non-null value is not boolean
     */
    private static function resolveBoolean(mixed $value, string $key): bool
    {
        if (!\is_bool($value)) {
            throw new \InvalidArgumentException('logger.' . $key . ' must be boolean.');
        }

        return $value;
    }

    /**
     * Validates an ordered selection of processor implementations.
     *
     * @param mixed $processors the selection, or null for legacy defaults
     *
     * @return null|list<class-string<ProcessorInterface>> the normalized processor classes
     *
     * @throws \InvalidArgumentException when classes do not implement the contract or list entries are invalid
     */
    private static function resolveProcessors(mixed $processors): ?array
    {
        if (null === $processors) {
            return null;
        }
        if (!\is_array($processors) || !array_is_list($processors)) {
            throw new \InvalidArgumentException('logger.processors must be a list of processor classes.');
        }
        $result = [];
        foreach ($processors as $id) {
            $processor = $id instanceof Processor ? $id->value : $id;
            if (
                !\is_string($processor) || !is_subclass_of($processor, ProcessorInterface::class)
                || \in_array($processor, $result, true)
            ) {
                throw new \InvalidArgumentException(
                    'logger.processors must contain unique ProcessorInterface classes.',
                );
            }
            $result[] = $processor;
        }

        return $result;
    }

    /**
     * Validates the finite output format.
     *
     * @param mixed $formatter the normalized format setting
     *
     * @return Formatter the selected formatter
     *
     * @throws \InvalidArgumentException when the value is not text or json
     */
    private static function resolveFormatter(mixed $formatter): Formatter
    {
        return (\is_string($formatter) ? Formatter::tryFrom($formatter) : null)
            ?? throw new \InvalidArgumentException('logger.formatter must be text or json.');
    }

    /**
     * Normalizes the timezone setting while preserving null as the fallback signal.
     *
     * @param mixed $timezone a timezone name, DateTimeZone instance, or null
     *
     * @return null|\DateTimeZone the normalized timezone, or null for a fallback
     *
     * @throws \InvalidArgumentException when a non-null timezone value is invalid
     */
    private static function resolveTimezone(mixed $timezone): ?\DateTimeZone
    {
        if (\is_string($timezone)) {
            try {
                $timezone = new \DateTimeZone($timezone);
            } catch (\Exception|\ValueError $exception) {
                throw new \InvalidArgumentException('logger.timezone must be a valid timezone name.', 0, $exception);
            }
        }
        if (null !== $timezone && !$timezone instanceof \DateTimeZone) {
            throw new \InvalidArgumentException(
                'logger.timezone or the container timezone must be a DateTimeZone, timezone name or null.',
            );
        }

        return $timezone;
    }
}
