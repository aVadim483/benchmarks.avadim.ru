<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Пик памяти процесса по данным ОС (RSS), а не по счётчику аллокатора PHP.
 *
 * memory_get_peak_usage() считает только то, что выделено через emalloc.
 * libxml — а значит SimpleXMLElement, DOMDocument и XMLReader::expand() —
 * работает своим malloc'ом, мимо этого счётчика и мимо memory_limit. Разница
 * не косметическая: разбор листа на 12 МБ XML через simplexml_load_string()
 * не меняет memory_get_peak_usage() вообще, а RSS процесса при этом вырастает
 * примерно на 313 МБ. Библиотека, строящая DOM листа, по счётчику PHP выглядит
 * в разы легче, чем она есть.
 *
 * Поэтому основная метрика памяти в бенчмарке — RSS, а счётчик PHP остаётся
 * рядом справочной цифрой.
 */
final class Memory
{
    /** Linux: /proc/self/status, поле VmHWM — high water mark резидентной памяти. */
    public const SOURCE_PROCFS = 'procfs';

    /** POSIX и Windows-сборка PHP: getrusage()['ru_maxrss']. */
    public const SOURCE_RUSAGE = 'getrusage';

    /** Платформа не отдаёт пик RSS — остаётся только счётчик PHP. */
    public const SOURCE_NONE = 'none';

    private static ?string $source = null;

    /**
     * Пик резидентной памяти процесса за всё время его жизни, в байтах.
     * Значение монотонно: освобождение памяти его не снижает.
     */
    public static function peakRss(): ?int
    {
        return match (self::source()) {
            self::SOURCE_PROCFS => self::fromProcStatus(),
            self::SOURCE_RUSAGE => self::fromRusage(),
            default             => null,
        };
    }

    public static function supported(): bool
    {
        return self::source() !== self::SOURCE_NONE;
    }

    public static function source(): string
    {
        if (self::$source !== null) {
            return self::$source;
        }

        if (self::fromProcStatus() !== null) {
            return self::$source = self::SOURCE_PROCFS;
        }
        if (self::fromRusage() !== null) {
            return self::$source = self::SOURCE_RUSAGE;
        }

        return self::$source = self::SOURCE_NONE;
    }

    public static function describe(): string
    {
        return match (self::source()) {
            self::SOURCE_PROCFS => 'RSS процесса (/proc/self/status, VmHWM)',
            self::SOURCE_RUSAGE => 'RSS процесса (getrusage, ru_maxrss)',
            default             => 'недоступен — показан счётчик кучи PHP',
        };
    }

    private static function fromProcStatus(): ?int
    {
        if (\DIRECTORY_SEPARATOR === '\\') {
            return null;
        }

        $status = @file_get_contents('/proc/self/status');
        if (!is_string($status) || preg_match('/^VmHWM:\s+(\d+)\s+kB/mi', $status, $m) !== 1) {
            return null;
        }

        $kilobytes = (int) $m[1];

        return $kilobytes > 0 ? $kilobytes * 1024 : null;
    }

    private static function fromRusage(): ?int
    {
        if (!\function_exists('getrusage')) {
            return null;
        }

        $usage = @getrusage();
        $maxrss = is_array($usage) ? (int) ($usage['ru_maxrss'] ?? 0) : 0;
        if ($maxrss <= 0) {
            return null;
        }

        // macOS отдаёт ru_maxrss в байтах, Linux и Windows-сборка PHP — в килобайтах.
        return PHP_OS_FAMILY === 'Darwin' ? $maxrss : $maxrss * 1024;
    }
}
