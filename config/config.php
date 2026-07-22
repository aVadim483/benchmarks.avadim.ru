<?php

declare(strict_types=1);

/**
 * Базовая конфигурация. Локальные переопределения — в config/config.local.php
 * (файл возвращает массив, который рекурсивно мержится поверх этого).
 */

return [
    // Путь к CLI-бинарнику PHP, которым запускаются воркеры.
    // null => автоопределение (см. App\Support\Environment::phpBinary()).
    'php_binary' => getenv('BENCH_PHP_BINARY') ?: null,

    'paths' => [
        'root'     => dirname(__DIR__),
        'fixtures' => dirname(__DIR__) . '/data/fixtures',
        'uploads'  => dirname(__DIR__) . '/data/uploads',
        'results'  => dirname(__DIR__) . '/data/results',
        'tmp'      => dirname(__DIR__) . '/data/tmp',
    ],

    // Настройки эталонного (CLI) прогона на подготовленных файлах
    'baseline' => [
        'repeats'      => 5,
        'timeout'      => 300,   // секунд на один запуск воркера
        'memory_limit' => '2048M',
    ],

    // Настройки пользовательских прогонов через веб-форму
    'web' => [
        'repeats'          => 3,
        'timeout'          => 60,     // секунд на один замер
        'total_budget'     => 420,    // секунд на весь прогон; дальше сценарии помечаются пропущенными
        'memory_limit'     => '1024M',
        'max_upload_bytes' => 25 * 1024 * 1024,
        'allowed_ext'      => ['xlsx', 'xlsm'],
        // Сколько прогонов с одного IP разрешено за окно
        'rate_limit'       => ['max' => 10, 'window' => 3600],
        // Сколько часов хранить загруженные файлы и результаты
        'retention_hours'  => 24,
    ],

    // Настройки процесса-воркера
    'worker' => [
        // Отключать Xdebug/opcache в воркере: без этого замеры бессмысленны.
        'ini' => [
            'xdebug.mode'        => 'off',
            'opcache.enable_cli' => '0',
            'error_reporting'    => (string) (E_ALL & ~E_DEPRECATED),
            'display_errors'     => '0',
            'log_errors'         => '0',
        ],
    ],

    // Порядок сценариев на странице результатов
    'modes' => ['read_all', 'first_row', 'to_array'],
];
