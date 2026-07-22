<?php

declare(strict_types=1);

/**
 * Генерация эталонных XLSX-файлов.
 *
 *   php bin/generate-fixtures.php            — создать недостающие
 *   php bin/generate-fixtures.php --force    — перегенерировать все
 *   php bin/generate-fixtures.php small-1k   — только указанные наборы
 */

use App\Fixture\FixtureGenerator;
use App\Support\Config;
use App\Support\Format;

require dirname(__DIR__) . '/vendor/autoload.php';

ini_set('memory_limit', '512M');

$argsRaw = \array_slice($argv, 1);
$force = \in_array('--force', $argsRaw, true);
$only = array_values(array_filter($argsRaw, static fn (string $a): bool => !str_starts_with($a, '--')));

$generator = new FixtureGenerator(Config::path('fixtures'));
$catalog = FixtureGenerator::catalog();

foreach ($catalog as $key => $spec) {
    if ($only !== [] && !\in_array($key, $only, true)) {
        continue;
    }

    $path = $generator->path($key);
    if (is_file($path) && !$force) {
        printf("· %-22s уже есть (%s)%s", $key, Format::bytes((int) filesize($path)), PHP_EOL);
        continue;
    }

    printf('→ %-22s %d × %d ... ', $key, $spec['rows'], $spec['cols']);
    $start = hrtime(true);
    $generator->generate($key);
    $elapsed = (hrtime(true) - $start) / 1e9;

    printf("готово за %.1f с, %s%s", $elapsed, Format::bytes((int) filesize($path)), PHP_EOL);
}

echo PHP_EOL, 'Каталог: ', Config::path('fixtures'), PHP_EOL;
