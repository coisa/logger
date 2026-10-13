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
use CoiSA\Logger\Enum\Formatter;
use CoiSA\Logger\Enum\Processor;
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
     * @var string The output path/stream, or empty for disabled file-only logging.
     */
    public string $filename;

    /**
     * @var \DateTimeZone|null The configured timezone, or null to allow a fallback.
     */
    public ?\DateTimeZone $timezone;

    /** @var bool Whether records may reach any sink. */
    public bool $enabled;

    /** @var bool Whether only a private regular local file is allowed. */
    public bool $fileOnly;

    /** @var list<Processor>|null Selected processors, or null for legacy defaults. */
    public ?array $processors;

    /** @var Formatter The selected record representation. */
    public Formatter $formatter;

    /**
     * Constructs a validated snapshot from the optional logger section.
     *
     * @param ConfigInterface $config The optional logger section, including sink and processor options.
     *
     * @throws \InvalidArgumentException When the section or a non-null setting is invalid.
     */
    public function __construct(ConfigInterface $config)
    {
        $options = self::resolveOptions($config);
        $this->fileOnly = self::resolveBoolean($options['file_only'] ?? false, 'file_only');
        $this->level = self::resolveLevel($options['level'] ?? LogLevel::DEBUG->value);
        $filename = $options['filename'] ?? ($this->fileOnly ? '' : self::DEFAULT_FILENAME);
        $this->filename = self::resolveFilename($filename, $this->fileOnly);
        $this->enabled = self::resolveBoolean($options['enabled'] ?? true, 'enabled')
            && (!$this->fileOnly || $this->filename !== '');
        $this->processors = self::resolveProcessors($options['processors'] ?? ($this->fileOnly ? [] : null));
        $this->formatter = self::resolveFormatter($options['formatter'] ?? Formatter::TEXT->value);
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
     * @param bool $fileOnly Whether blank destinations disable logging and streams are forbidden.
     *
     * @return string The destination, or empty for disabled file-only logging.
     *
     * @throws \InvalidArgumentException When the destination type, contents or file-only scheme is invalid.
     */
    private static function resolveFilename(mixed $filename, bool $fileOnly): string
    {
        if ($fileOnly && is_string($filename) && trim($filename) === '') {
            return '';
        }
        if (
            !is_string($filename) || trim($filename) === '' || str_contains($filename, "\0")
            || ($fileOnly && preg_match('/^[a-z][a-z0-9+.-]*:/i', $filename))
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
     * @param mixed $value The normalized switch.
     * @param string $key The diagnostic setting name.
     * @return bool The validated switch.
     * @throws \InvalidArgumentException When a non-null value is not boolean.
     */
    private static function resolveBoolean(mixed $value, string $key): bool
    {
        if (!is_bool($value)) {
            throw new \InvalidArgumentException('logger.' . $key . ' must be boolean.');
        }
        return $value;
    }

    /**
     * Validates a finite ordered processor selection.
     *
     * @param mixed $processors The selection, or null for legacy defaults.
     * @return list<Processor>|null The normalized enum selection.
     * @throws \InvalidArgumentException When IDs, list shape or duplicates are invalid.
     */
    private static function resolveProcessors(mixed $processors): ?array
    {
        if ($processors === null) {
            return null;
        }
        if (!is_array($processors) || !array_is_list($processors)) {
            throw new \InvalidArgumentException('logger.processors must be a list of supported identifiers.');
        }
        $result = [];
        foreach ($processors as $id) {
            $processor = is_string($id) ? Processor::tryFrom($id) : null;
            if ($processor === null || in_array($processor, $result, true)) {
                throw new \InvalidArgumentException('logger.processors contains an unknown or duplicate identifier.');
            }
            $result[] = $processor;
        }
        return $result;
    }

    /**
     * Validates the finite output format.
     *
     * @param mixed $formatter The normalized format setting.
     * @return Formatter The selected formatter.
     * @throws \InvalidArgumentException When the value is not text or json.
     */
    private static function resolveFormatter(mixed $formatter): Formatter
    {
        return (is_string($formatter) ? Formatter::tryFrom($formatter) : null)
            ?? throw new \InvalidArgumentException('logger.formatter must be text or json.');
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
