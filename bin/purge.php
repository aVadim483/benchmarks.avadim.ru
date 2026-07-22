<?php

declare(strict_types=1);

/**
 * Удаление старых прогонов, загруженных файлов и временных данных.
 * Веб-приложение делает то же самое случайным образом раз в ~100 запросов,
 * но на боевом сервере лучше повесить это на cron:
 *
 *   0 * * * * php /path/to/bin/purge.php
 */

use App\Storage\ResultStore;
use App\Support\Config;

require dirname(__DIR__) . '/vendor/autoload.php';

$hours = (int) ($argv[1] ?? Config::get('web.retention_hours', 24));
$removed = (new ResultStore())->purge($hours);

printf('Удалено прогонов старше %d ч: %d%s', $hours, $removed, PHP_EOL);
