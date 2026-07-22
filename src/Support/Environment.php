<?php

declare(strict_types=1);

namespace App\Support;

final class Environment
{
    private static ?string $binary = null;

    /**
     * Путь к CLI-бинарнику PHP. В SAPI cli это сам PHP_BINARY, под fpm/apache —
     * PHP_BINARY указывает на php-fpm, поэтому ищем php(.exe) рядом с PHP_BINDIR.
     */
    public static function phpBinary(): string
    {
        if (self::$binary !== null) {
            return self::$binary;
        }

        $configured = Config::get('php_binary');
        if (is_string($configured) && $configured !== '') {
            return self::$binary = $configured;
        }

        if (PHP_SAPI === 'cli' && PHP_BINARY !== '') {
            return self::$binary = PHP_BINARY;
        }

        $suffix = self::isWindows() ? '.exe' : '';
        foreach ([PHP_BINDIR, dirname(PHP_BINARY)] as $dir) {
            $candidate = $dir . DIRECTORY_SEPARATOR . 'php' . $suffix;
            if (is_file($candidate)) {
                return self::$binary = $candidate;
            }
        }

        return self::$binary = 'php' . $suffix;
    }

    public static function isWindows(): bool
    {
        return \DIRECTORY_SEPARATOR === '\\';
    }

    public static function canSpawnProcesses(): bool
    {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return function_exists('proc_open') && !in_array('proc_open', $disabled, true);
    }

    /** @return array<string, string> */
    public static function describe(): array
    {
        return [
            'PHP'          => PHP_VERSION . ' (' . PHP_SAPI . ')',
            'ОС'           => php_uname('s') . ' ' . php_uname('r') . ' / ' . php_uname('m'),
            'CPU'          => self::cpuModel(),
            'Zend Engine'  => zend_version(),
            'ext-zip'      => phpversion('zip') ?: 'нет',
            'libxml'       => LIBXML_DOTTED_VERSION,
            'Замер памяти' => Memory::describe(),
            'Xdebug'       => extension_loaded('xdebug')
                ? phpversion('xdebug') . ' (в воркерах выключен)'
                : 'не установлен',
            'OPcache'      => extension_loaded('Zend OPcache')
                ? 'установлен (в воркерах выключен)'
                : 'не установлен',
        ];
    }

    public static function cpuModel(): string
    {
        if (self::isWindows()) {
            $name = getenv('PROCESSOR_IDENTIFIER');

            return is_string($name) && $name !== '' ? $name : 'неизвестно';
        }

        $cpuinfo = @file_get_contents('/proc/cpuinfo');
        if (is_string($cpuinfo) && preg_match('/^model name\s*:\s*(.+)$/m', $cpuinfo, $m) === 1) {
            return trim($m[1]);
        }

        return 'неизвестно';
    }
}
