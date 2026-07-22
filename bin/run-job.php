<?php

declare(strict_types=1);

/**
 * Выполняет пользовательский прогон в фоне и обновляет файл состояния,
 * который опрашивает страница результатов.
 *
 *   php bin/run-job.php --job=<id>
 */

use App\Bench\Benchmark;
use App\Bench\Mode;
use App\Bench\ProcessRunner;
use App\Bench\Registry;
use App\Storage\JobStore;
use App\Support\Config;
use App\Support\Environment;

require dirname(__DIR__) . '/vendor/autoload.php';

set_time_limit(0);
ignore_user_abort(true);

$options = getopt('', ['job:']);
$id = (string) ($options['job'] ?? '');

$store = new JobStore();
$job = $store->load($id);

if ($job === null) {
    fwrite(STDERR, "Прогон не найден: {$id}" . PHP_EOL);
    exit(1);
}

$file = (string) $job['upload']['path'];

$job['status'] = 'running';
$job['started_at'] = (new DateTimeImmutable())->format(DATE_ATOM);
$store->save($job);

$runner = new ProcessRunner(
    (int) Config::get('web.timeout', 60),
    (string) Config::get('web.memory_limit', '1024M'),
);
$benchmark = new Benchmark((int) Config::get('web.repeats', 3), $runner);

$budget = (float) Config::get('web.total_budget', 420);
$start = microtime(true);

$combinations = [];
foreach (Mode::all() as $mode) {
    foreach (Registry::ids() as $adapterId) {
        $combinations[] = [$mode, $adapterId];
    }
}

$job['progress'] = ['done' => 0, 'total' => count($combinations), 'current' => null];
$store->save($job);

$done = 0;
foreach ($combinations as [$mode, $adapterId]) {
    $job['progress']['current'] = ['mode' => $mode->value, 'adapter' => $adapterId];
    $store->save($job);

    if (microtime(true) - $start > $budget) {
        $job['results'][$mode->value][$adapterId] = [
            'status' => 'skipped',
            'error'  => 'Пропущено: исчерпан общий лимит времени на прогон',
        ];
    } else {
        $measured = $benchmark->measureFile($file, [$mode], [$adapterId]);
        $job['results'][$mode->value][$adapterId] = $measured[$mode->value][$adapterId];
    }

    $job['progress']['done'] = ++$done;
    $store->save($job);
}

$job['status'] = 'done';
$job['finished_at'] = (new DateTimeImmutable())->format(DATE_ATOM);
$job['duration_s'] = round(microtime(true) - $start, 1);
$job['progress']['current'] = null;
$job['environment'] = Environment::describe();
$job['libraries'] = Registry::describe();
$store->save($job);

// Исходный файл пользователя больше не нужен — данные могут быть чувствительными.
if (is_file($file)) {
    @unlink($file);
}

echo "Прогон {$id} завершён за {$job['duration_s']} с", PHP_EOL;
