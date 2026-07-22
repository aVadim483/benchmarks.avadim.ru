<?php

declare(strict_types=1);

/**
 * Замер одной пары (библиотека, сценарий) в изолированном процессе.
 *
 * Изоляция принципиальна: memory_get_peak_usage() показывает пик по процессу,
 * поэтому мерить несколько библиотек в одном процессе бессмысленно — вторая
 * унаследует пик первой, а её классы уже будут загружены.
 *
 * Использование:
 *   php bin/worker.php --adapter=openspout --mode=read_all --file=book.xlsx --out=result.json
 */

use App\Bench\Mode;
use App\Bench\Registry;

require dirname(__DIR__) . '/vendor/autoload.php';

$options = getopt('', ['adapter:', 'mode:', 'file:', 'out:']);
$outFile = $options['out'] ?? null;

$emit = static function (array $payload) use (&$outFile): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    if (is_string($outFile) && $outFile !== '') {
        file_put_contents($outFile, $json);
    } else {
        echo $json, PHP_EOL;
    }
};

// Фатальные ошибки (в первую очередь — исчерпание памяти) тоже должны стать результатом.
register_shutdown_function(static function () use ($emit): void {
    $error = error_get_last();
    if ($error !== null && ($error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR)) !== 0) {
        $isOom = str_contains($error['message'], 'Allowed memory size');
        $emit([
            'ok'     => false,
            'status' => $isOom ? 'oom' : 'error',
            'error'  => $isOom
                ? 'Превышен лимит памяти процесса (' . ini_get('memory_limit') . ')'
                : $error['message'],
        ]);
    }
});

try {
    $adapterId = (string) ($options['adapter'] ?? '');
    $mode = Mode::tryFrom((string) ($options['mode'] ?? ''));
    $file = (string) ($options['file'] ?? '');

    if ($mode === null) {
        throw new InvalidArgumentException('Неизвестный сценарий: ' . (string) ($options['mode'] ?? ''));
    }
    if (!is_file($file)) {
        throw new RuntimeException("Файл не найден: {$file}");
    }

    $adapter = Registry::make($adapterId);

    // Прогреваем файловый кэш ОС, чтобы первый замер не платил за холодное чтение с диска.
    $handle = fopen($file, 'rb');
    if ($handle !== false) {
        while (!feof($handle)) {
            fread($handle, 1 << 20);
        }
        fclose($handle);
    }

    gc_collect_cycles();
    $baseline = memory_get_usage();
    $baselineReal = memory_get_usage(true);
    memory_reset_peak_usage();

    $start = hrtime(true);
    $tally = $adapter->run($mode, $file);
    $elapsed = (hrtime(true) - $start) / 1e6;

    $peak = memory_get_peak_usage();
    $peakReal = memory_get_peak_usage(true);

    if ($tally === null) {
        $emit(['ok' => false, 'status' => 'unsupported', 'error' => 'Сценарий не поддержан библиотекой']);
        exit(0);
    }

    $emit([
        'ok'            => true,
        'status'        => 'ok',
        'adapter'       => $adapterId,
        'mode'          => $mode->value,
        'time_ms'       => round($elapsed, 3),
        'peak_bytes'    => $peak,
        'peak_delta'    => max(0, $peak - $baseline),
        'peak_real'     => $peakReal,
        'baseline'      => $baseline,
        'baseline_real' => $baselineReal,
        'rows'          => $tally->rows,
        'cells'         => $tally->cells,
        'bytes'         => $tally->bytes,
    ]);
} catch (Throwable $e) {
    $emit([
        'ok'     => false,
        'status' => 'error',
        'error'  => $e::class . ': ' . $e->getMessage(),
    ]);
}
