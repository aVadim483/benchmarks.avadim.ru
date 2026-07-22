<?php

declare(strict_types=1);

namespace App\Bench;

use App\Support\Config;
use App\Support\Environment;

/**
 * Запуск воркера отдельным процессом PHP с жёстким таймаутом.
 */
final class ProcessRunner
{
    public function __construct(
        private readonly int $timeout,
        private readonly string $memoryLimit,
    ) {
    }

    /**
     * @return array<string, mixed> Результат воркера либо описание сбоя
     */
    public function run(string $adapterId, Mode $mode, string $file): array
    {
        if (!Environment::canSpawnProcesses()) {
            return [
                'ok'     => false,
                'status' => 'error',
                'error'  => 'Функция proc_open отключена — запуск изолированных замеров невозможен',
            ];
        }

        $out = tempnam(Config::path('tmp'), 'bench');
        if ($out === false) {
            return ['ok' => false, 'status' => 'error', 'error' => 'Не удалось создать временный файл'];
        }

        $command = $this->buildCommand($adapterId, $mode, $file, $out);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, Config::get('paths.root'));
        if (!\is_resource($process)) {
            @unlink($out);

            return ['ok' => false, 'status' => 'error', 'error' => 'Не удалось запустить процесс PHP'];
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $deadline = microtime(true) + $this->timeout;
        $stdout = '';
        $stderr = '';
        $timedOut = false;

        while (true) {
            $status = proc_get_status($process);
            $stdout .= (string) stream_get_contents($pipes[1]);
            $stderr .= (string) stream_get_contents($pipes[2]);

            if (!$status['running']) {
                break;
            }
            if (microtime(true) > $deadline) {
                $timedOut = true;
                proc_terminate($process, 9);
                break;
            }
            usleep(2000);
        }

        $stdout .= (string) stream_get_contents($pipes[1]);
        $stderr .= (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $raw = is_file($out) ? (string) file_get_contents($out) : '';
        @unlink($out);

        if ($timedOut) {
            return [
                'ok'     => false,
                'status' => 'timeout',
                'error'  => "Превышен лимит времени ({$this->timeout} с)",
            ];
        }

        $decoded = $raw !== '' ? json_decode($raw, true) : null;
        if (is_array($decoded)) {
            return $decoded;
        }

        $message = trim($stderr) !== '' ? trim($stderr) : trim($stdout);
        if ($message === '') {
            // Молча умерший процесс — почти всегда убитый ОС за расход памяти:
            // аллокации libxml идут мимо memory_limit, поэтому PHP не успевает
            // сообщить «Allowed memory size» и никакого stderr не остаётся.
            $message = "Процесс завершился с кодом {$exitCode} без результата"
                . ' (вероятно, снят операционной системой за расход памяти —'
                . ' libxml не подчиняется memory_limit)';
        }

        return [
            'ok'     => false,
            'status' => $exitCode === 0 ? 'error' : 'crash',
            'error'  => mb_substr($message, 0, 500),
        ];
    }

    private function buildCommand(string $adapterId, Mode $mode, string $file, string $out): string
    {
        $args = [
            Environment::phpBinary(),
            '-d', 'memory_limit=' . $this->memoryLimit,
        ];

        /** @var array<string, string> $ini */
        $ini = Config::get('worker.ini', []);
        foreach ($ini as $key => $value) {
            $args[] = '-d';
            $args[] = "{$key}={$value}";
        }

        $args[] = Config::get('paths.root') . '/bin/worker.php';
        $args[] = '--adapter=' . $adapterId;
        $args[] = '--mode=' . $mode->value;
        $args[] = '--file=' . $file;
        $args[] = '--out=' . $out;

        return implode(' ', array_map(self::escape(...), $args));
    }

    /**
     * escapeshellarg() под Windows ломает пути с обратными слэшами и не даёт
     * передать аргументы вида --file=C:\path, поэтому квотируем сами.
     */
    private static function escape(string $arg): string
    {
        if (!Environment::isWindows()) {
            return escapeshellarg($arg);
        }

        if ($arg !== '' && preg_match('/[\s"^&|<>()]/', $arg) !== 1) {
            return $arg;
        }

        return '"' . str_replace('"', '""', $arg) . '"';
    }
}
