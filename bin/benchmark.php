<?php

declare(strict_types=1);

/**
 * Эталонный прогон по всем сгенерированным файлам. Результат кладётся в
 * data/results/baseline.json и становится содержимым главной страницы сайта.
 *
 *   php bin/benchmark.php
 *   php bin/benchmark.php --repeats=3 --only=small-1k,medium-20k
 */

use App\Bench\Benchmark;
use App\Bench\FileProbe;
use App\Bench\Mode;
use App\Bench\ProcessRunner;
use App\Bench\Registry;
use App\Fixture\FixtureGenerator;
use App\Storage\ResultStore;
use App\Support\Config;
use App\Support\Environment;
use App\Support\Format;
use App\Support\Memory;
use App\Web\Presenter;

require dirname(__DIR__) . '/vendor/autoload.php';

$options = getopt('', ['repeats::', 'only::', 'timeout::']);
$repeats = (int) ($options['repeats'] ?? Config::get('baseline.repeats', 5));
$timeout = (int) ($options['timeout'] ?? Config::get('baseline.timeout', 300));
$only = isset($options['only']) ? explode(',', (string) $options['only']) : null;

$generator = new FixtureGenerator(Config::path('fixtures'));
$catalog = FixtureGenerator::catalog();

$missing = [];
foreach ($catalog as $key => $_) {
    if (!is_file($generator->path($key))) {
        $missing[] = $key;
    }
}
if ($missing !== []) {
    fwrite(STDERR, "Не хватает файлов: " . implode(', ', $missing) . PHP_EOL
        . 'Сначала выполните: php bin/generate-fixtures.php' . PHP_EOL);
    exit(1);
}

$runner = new ProcessRunner($timeout, (string) Config::get('baseline.memory_limit', '2048M'));
$benchmark = new Benchmark($repeats, $runner);

echo 'PHP для воркеров: ', Environment::phpBinary(), PHP_EOL;
echo 'Повторов на замер: ', $repeats, PHP_EOL;
echo 'Память меряется как: ', Memory::describe(), PHP_EOL;
if (!Memory::supported()) {
    fwrite(STDERR, 'Внимание: пик RSS недоступен, память будет занижена для библиотек на libxml.' . PHP_EOL);
}
echo PHP_EOL;

$datasets = [];
$totalStart = hrtime(true);

foreach ($catalog as $key => $spec) {
    if ($only !== null && !\in_array($key, $only, true)) {
        continue;
    }

    $path = $generator->path($key);
    echo '### ', $spec['title'], '  (', Format::bytes((int) filesize($path)), ')', PHP_EOL;

    $results = [];
    foreach (Mode::all() as $mode) {
        foreach (Registry::ids() as $adapterId) {
            printf('  %-11s %-18s ', $mode->value, $adapterId);
            $single = $benchmark->measureFile($path, [$mode], [$adapterId]);
            $row = $single[$mode->value][$adapterId];
            $results[$mode->value][$adapterId] = $row;

            if ($row['status'] === 'ok') {
                printf("%10s  %10s  %s %s%s",
                    Format::ms((float) $row['time_ms']),
                    Format::bytes((int) $row[Presenter::METRIC_MEMORY]),
                    Format::number((int) $row['rows']),
                    Format::plural((int) $row['rows'], 'строка', 'строки', 'строк'),
                    PHP_EOL,
                );
            } else {
                printf("%s: %s%s", strtoupper($row['status']), $row['error'], PHP_EOL);
            }
        }
    }

    $datasets[] = [
        'key'   => $key,
        'title' => $spec['title'],
        'about' => $spec['about'],
        'file'  => FileProbe::inspect($path, basename($path)),
        'results' => $results,
    ];

    echo PHP_EOL;
}

$baseline = [
    'created_at'  => (new \DateTimeImmutable())->format(\DATE_ATOM),
    'duration_s'  => round((hrtime(true) - $totalStart) / 1e9, 1),
    'settings'    => [
        'repeats'      => $repeats,
        'timeout'      => $timeout,
        'memory_limit' => Config::get('baseline.memory_limit'),
    ],
    'environment' => Environment::describe(),
    'libraries'   => Registry::describe(),
    'datasets'    => $datasets,
];

(new ResultStore())->saveBaseline($baseline);

printf('Готово за %s. Результат: data/results/baseline.json%s',
    Format::ms($baseline['duration_s'] * 1000), PHP_EOL);
