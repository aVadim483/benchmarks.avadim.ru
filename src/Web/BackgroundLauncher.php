<?php

declare(strict_types=1);

namespace App\Web;

use App\Support\Config;
use App\Support\Environment;

/**
 * Запуск фонового процесса, переживающего завершение HTTP-запроса.
 */
final class BackgroundLauncher
{
    public static function runJob(string $jobId): bool
    {
        if (!Environment::canSpawnProcesses()) {
            return false;
        }

        $php = Environment::phpBinary();
        $script = Config::get('paths.root') . '/bin/run-job.php';
        $log = Config::path('tmp') . '/job-' . $jobId . '.log';

        if (Environment::isWindows()) {
            // start /B отвязывает процесс от текущей консоли и не ждёт его завершения
            $command = sprintf(
                'start /B "" %s -d xdebug.mode=off %s --job=%s > %s 2>&1',
                self::quote($php),
                self::quote($script),
                $jobId,
                self::quote($log),
            );
            $handle = popen('cmd /C ' . $command, 'r');
            if ($handle === false) {
                return false;
            }
            pclose($handle);

            return true;
        }

        $command = sprintf(
            '%s -d xdebug.mode=off %s --job=%s > %s 2>&1 &',
            escapeshellarg($php),
            escapeshellarg($script),
            escapeshellarg($jobId),
            escapeshellarg($log),
        );
        exec($command);

        return true;
    }

    private static function quote(string $value): string
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }
}
