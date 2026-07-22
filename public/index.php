<?php

declare(strict_types=1);

use App\Bench\Mode;
use App\Bench\Registry;
use App\Fixture\FixtureGenerator;
use App\Storage\JobStore;
use App\Storage\ResultStore;
use App\Support\Config;
use App\Support\Environment;
use App\Support\Format;
use App\Support\Memory;
use App\Support\RateLimiter;
use App\Web\BackgroundLauncher;
use App\Web\UploadException;
use App\Web\UploadHandler;
use App\Web\View;

require dirname(__DIR__) . '/vendor/autoload.php';

$path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$path = '/' . trim(rawurldecode($path), '/');
$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');

$store = new ResultStore();
$jobs = new JobStore();

// Периодическая уборка старых прогонов — раз в сотню запросов, без cron.
if (random_int(1, 100) === 1) {
    $store->purge((int) Config::get('web.retention_hours', 24));
}

$json = static function (mixed $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    exit;
};

$notFound = static function (string $message = 'Страница не найдена'): never {
    echo View::render('error', ['title' => 'Не найдено', 'message' => $message], 404);
    exit;
};

// --- Маршруты -------------------------------------------------------------

// Точка входа не должна светиться в адресной строке
if ($path === '/index.php') {
    header('Location: /', true, 301);
    exit;
}

if ($path === '/' && $method === 'GET') {
    $baseline = $store->loadBaseline();

    echo View::render('home', [
        'title'     => 'FastExcelReader против конкурентов — бенчмарк чтения XLSX в PHP',
        'baseline'  => $baseline,
        'libraries' => Registry::describe(),
        'limits'    => [
            'max_bytes' => (int) Config::get('web.max_upload_bytes'),
            'ext'       => (array) Config::get('web.allowed_ext'),
            'repeats'   => (int) Config::get('web.repeats'),
        ],
        'canRun'    => Environment::canSpawnProcesses(),
    ]);
    exit;
}

if ($path === '/methodology' && $method === 'GET') {
    echo View::render('methodology', [
        'title'     => 'Методика измерений',
        'libraries' => Registry::describe(),
        'settings'  => [
            'baseline'      => (array) Config::get('baseline'),
            'web'           => (array) Config::get('web'),
            'ini'           => (array) Config::get('worker.ini'),
            'memory_metric' => Memory::describe(),
        ],
        'datasets'  => FixtureGenerator::catalog(),
    ]);
    exit;
}

if ($path === '/run' && $method === 'POST') {
    if (!Environment::canSpawnProcesses()) {
        echo View::render('error', [
            'title'   => 'Прогон недоступен',
            'message' => 'На этом сервере отключена функция proc_open, поэтому изолированные замеры невозможны.',
        ], 503);
        exit;
    }

    /** @var array{max:int, window:int} $limitCfg */
    $limitCfg = (array) Config::get('web.rate_limit', ['max' => 10, 'window' => 3600]);
    $limiter = new RateLimiter($limitCfg['max'], $limitCfg['window']);
    $limit = $limiter->hit(RateLimiter::clientKey());

    if (!$limit['allowed']) {
        echo View::render('error', [
            'title'   => 'Слишком много прогонов',
            'message' => sprintf(
                'С вашего адреса уже выполнено %d %s за последний час. Попробуйте снова через %d мин.',
                $limitCfg['max'],
                Format::plural($limitCfg['max'], 'прогон', 'прогона', 'прогонов'),
                (int) ceil($limit['retry_after'] / 60),
            ),
        ], 429);
        exit;
    }

    $jobId = JobStore::newId();

    try {
        $upload = (new UploadHandler())->accept($_FILES['file'] ?? [], $jobId);
    } catch (UploadException $e) {
        echo View::render('error', [
            'title'   => 'Файл не принят',
            'message' => $e->getMessage(),
            'back'    => '/',
        ], 400);
        exit;
    }

    $jobs->save([
        'id'         => $jobId,
        'status'     => 'queued',
        'source'     => 'upload',
        'created_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        'upload'     => ['path' => $upload['path'], 'name' => $upload['name']],
        'file'       => $upload['probe'],
        'settings'   => [
            'repeats'      => (int) Config::get('web.repeats'),
            'timeout'      => (int) Config::get('web.timeout'),
            'memory_limit' => (string) Config::get('web.memory_limit'),
        ],
        'progress'   => ['done' => 0, 'total' => count(Mode::all()) * count(Registry::ids()), 'current' => null],
        'results'    => [],
    ]);

    BackgroundLauncher::runJob($jobId);

    header('Location: /r/' . $jobId, true, 303);
    exit;
}

if (preg_match('~^/r/([a-f0-9]{16})$~', $path, $m) === 1 && $method === 'GET') {
    $job = $jobs->load($m[1]);
    if ($job === null) {
        $notFound('Прогон не найден. Возможно, он уже удалён — результаты хранятся '
            . Config::get('web.retention_hours') . ' ч.');
    }

    echo View::render('run', [
        'title'     => 'Результаты для ' . ($job['file']['name'] ?? 'файла'),
        'job'       => $job,
        'libraries' => $job['libraries'] ?? Registry::describe(),
    ]);
    exit;
}

if (preg_match('~^/api/run/([a-f0-9]{16})$~', $path, $m) === 1 && $method === 'GET') {
    $job = $jobs->load($m[1]);
    if ($job === null) {
        $json(['error' => 'not_found'], 404);
    }
    unset($job['upload']);
    $json($job);
}

if ($path === '/api/baseline' && $method === 'GET') {
    $baseline = $store->loadBaseline();
    if ($baseline === null) {
        $json(['error' => 'not_ready'], 404);
    }
    $json($baseline);
}

if (preg_match('~^/fixtures/([a-z0-9-]+)\.xlsx$~', $path, $m) === 1 && $method === 'GET') {
    $key = $m[1];
    if (!array_key_exists($key, FixtureGenerator::catalog())) {
        $notFound('Такого тестового файла нет');
    }

    $file = Config::path('fixtures') . '/' . $key . '.xlsx';
    if (!is_file($file)) {
        $notFound('Файл ещё не сгенерирован');
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Length: ' . filesize($file));
    header('Content-Disposition: attachment; filename="' . $key . '.xlsx"');
    readfile($file);
    exit;
}

if ($path === '/health' && $method === 'GET') {
    $json([
        'ok'            => true,
        'proc_open'     => Environment::canSpawnProcesses(),
        'php_binary'    => Environment::phpBinary(),
        'memory_metric' => Memory::supported() ? Memory::source() : 'php-heap',
        'baseline'      => $store->loadBaseline() !== null,
        'libraries'     => array_column(Registry::describe(), 'version', 'id'),
    ]);
}

$notFound();
