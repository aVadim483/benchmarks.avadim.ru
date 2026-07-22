# Бенчмарк PHP-библиотек чтения XLSX

Сравнение **avadim/fast-excel-reader** с основными конкурентами по скорости чтения и пиковому
потреблению памяти. Проект — готовый сайт: показывает результаты на подготовленных файлах и
позволяет любому посетителю без регистрации загрузить свой XLSX и прогнать те же замеры.

## Что сравнивается

| Библиотека | Пакет | Подход |
|---|---|---|
| FastExcelReader | `avadim/fast-excel-reader` | потоковый XMLReader, генератор `nextRow()` |
| OpenSpout | `openspout/openspout` | потоковые итераторы, кэш sharedStrings на диске |
| PhpSpreadsheet | `phpoffice/phpspreadsheet` | полная объектная модель книги, режим `setReadDataOnly(true)` |
| SimpleXLSX | `shuchkin/simplexlsx` | один класс без зависимостей, лист целиком в `SimpleXMLElement` |

Три сценария на каждый файл:

* `read_all` — пройти все строки первого листа самым экономичным штатным способом библиотеки;
* `first_row` — открыть файл и получить только первую строку (цена «входа» в документ);
* `to_array` — получить весь лист одним PHP-массивом.

## Требования

* PHP 8.4+ с расширениями `zip`, `xmlreader`, `mbstring`, `json`
* Composer
* доступная функция `proc_open` (каждый замер выполняется в отдельном процессе)

## Установка и запуск

```bash
composer install
php bin/generate-fixtures.php     # сгенерировать 7 тестовых файлов (~17 МБ)
php bin/benchmark.php             # эталонный прогон -> data/results/baseline.json
php -S 127.0.0.1:8080 -t public   # локальный просмотр
```

Полезные флаги:

```bash
php bin/generate-fixtures.php --force            # перегенерировать файлы
php bin/benchmark.php --repeats=3 --only=small-1k,medium-20k
php bin/purge.php 24                             # удалить прогоны старше 24 ч
```

## Как это устроено

```
bin/worker.php          один замер (библиотека × сценарий) в изолированном процессе
bin/run-job.php         фоновый прогон пользовательского файла
src/Bench/Adapter/      по классу на библиотеку — весь измеряемый код
src/Bench/Benchmark.php оркестрация повторов, медиана, обработка сбоев
src/Web/                презентация: бары, выжимки, приём загрузок
public/index.php        фронт-контроллер
config/config.php       лимиты, таймауты, пути
```

Ключевые решения по корректности замеров описаны на странице `/methodology`, коротко:

* **каждый замер — новый процесс PHP**, иначе `memory_get_peak_usage()` показывает пик предыдущей
  библиотеки, а её классы уже загружены автолоадером;
* **Xdebug и OPcache выключены** в воркерах (`-d xdebug.mode=off -d opcache.enable_cli=0`);
* перед замером файл прогревается чтением с диска, вызывается `gc_collect_cycles()` и
  `memory_reset_peak_usage()`;
* показывается медиана нескольких повторов, минимум и максимум видны в подробной таблице;
* каждый адаптер возвращает число прочитанных строк и непустых ячеек — видно, что библиотеки
  сделали одинаковую работу;
* таймаут и нехватка памяти не прячутся, а попадают в отчёт как результат.

## Развёртывание

Корень сайта — каталог `public/`. Каталог `data/` должен быть доступен PHP на запись и **не должен**
раздаваться веб-сервером.

nginx + php-fpm:

```nginx
server {
    server_name example.com;
    root /var/www/fast-excel-benchmark/public;
    index index.php;

    client_max_body_size 32m;

    location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

Для Apache достаточно `public/.htaccess` из репозитория.

Если PHP-FPM запускается не тем же бинарником, что CLI, укажите путь явно:

```php
// config/config.local.php
return ['php_binary' => '/usr/bin/php8.4'];
```

Проверить готовность окружения: `GET /health`.

Уборка старых данных вешается на cron:

```cron
0 * * * * php /var/www/fast-excel-benchmark/bin/purge.php
```

### Обновление боевого сервера

Рабочий каталог сайта — обычный клон репозитория, поэтому обновление сводится к:

```bash
cd /var/www/avadim/data/www/benchmarks.avadim.ru
git pull
/opt/php84/bin/php /usr/local/bin/composer install --no-dev --optimize-autoloader
```

Всё, что специфично для машины, лежит вне репозитория и `git pull` его не трогает:
`config/config.local.php` (путь к CLI-бинарнику PHP), `data/fixtures/*.xlsx`,
`data/results/baseline.json`, `data/uploads/`, `vendor/`.

Перегенерировать эталонные результаты после изменения адаптеров или наборов данных:

```bash
/opt/php84/bin/php bin/generate-fixtures.php --force
/opt/php84/bin/php bin/benchmark.php --repeats=5
```

Прогон занимает несколько минут и переписывает `data/results/baseline.json` — до его
завершения сайт продолжает показывать предыдущие цифры.

## Ограничения загрузок

Задаются в `config/config.php` (секция `web`): размер файла, допустимые расширения, таймаут на
замер, общий бюджет времени на прогон, лимит прогонов с одного IP и срок хранения отчётов.
Загруженный файл удаляется сразу после прогона; в отчёт попадают только размеры, счётчики и тайминги —
содержимое ячеек не сохраняется и не выводится.

## API

* `GET /api/baseline` — эталонный прогон целиком в JSON
* `GET /api/run/{id}` — состояние и результаты пользовательского прогона
* `GET /fixtures/{key}.xlsx` — скачать тестовый файл
* `GET /health` — состояние окружения

## Добавить свою библиотеку

1. `composer require ...`
2. класс-наследник `App\Bench\Adapter` в `src/Bench/Adapter/`
3. добавить его в `App\Bench\Registry::ADAPTERS`
4. `php bin/benchmark.php`

## Лицензия

MIT.
