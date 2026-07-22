<?php

declare(strict_types=1);

namespace App\Fixture;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Генерация эталонных файлов.
 *
 * Пишем нейтральной библиотекой (OpenSpout), а не одной из сравниваемых по чтению
 * «своих» — чтобы формат файла не был подогнан под конкретный парсер. Данные
 * детерминированы (фиксированный seed), поэтому набор воспроизводим.
 */
final class FixtureGenerator
{
    private const FIRST_NAMES = [
        'Александр', 'Мария', 'Дмитрий', 'Анна', 'Сергей', 'Елена', 'Иван', 'Ольга',
        'Andreas', 'Sofia', 'Ludovic', 'Chloé', 'Jürgen', 'Renée', 'Tomáš', 'Zoë',
    ];

    private const LAST_NAMES = [
        'Иванов', 'Петрова', 'Сидоров', 'Кузнецова', 'Смирнов', 'Волкова',
        'Müller', 'Sørensen', 'Nowak', 'Da Silva', 'O’Brien', 'Ünal',
    ];

    private const CITIES = [
        'Москва', 'Санкт-Петербург', 'Новосибирск', 'Екатеринбург', 'Казань',
        'München', 'Kraków', 'Lyon', 'İstanbul', 'Malmö',
    ];

    private const STATUSES = ['новый', 'в работе', 'оплачен', 'отгружен', 'отменён', 'возврат'];

    /**
     * Описание набора: ключ => параметры.
     *
     * @return array<string, array{rows:int, cols:int, shape:string, inline:bool, title:string, about:string}>
     */
    public static function catalog(): array
    {
        return [
            'small-1k' => [
                'rows' => 1_000, 'cols' => 12, 'shape' => 'mixed', 'inline' => false,
                'title' => 'Небольшой отчёт — 1 000 × 12',
                'about' => 'Типичная выгрузка из админки: смешанные типы, немного данных.',
            ],
            'medium-20k' => [
                'rows' => 20_000, 'cols' => 15, 'shape' => 'mixed', 'inline' => false,
                'title' => 'Средняя выгрузка — 20 000 × 15',
                'about' => 'Рабочий объём для импорта заказов или товаров.',
            ],
            'large-100k' => [
                'rows' => 100_000, 'cols' => 10, 'shape' => 'mixed', 'inline' => false,
                'title' => 'Большая выгрузка — 100 000 × 10',
                'about' => 'Миллион ячеек. Здесь разница между потоковым и объектным чтением становится решающей.',
            ],
            'wide-2k-x-150' => [
                'rows' => 2_000, 'cols' => 150, 'shape' => 'mixed', 'inline' => false,
                'title' => 'Широкая таблица — 2 000 × 150',
                'about' => 'Мало строк, но очень много колонок — нагрузка на разбор адресов ячеек.',
            ],
            'strings-40k-shared' => [
                'rows' => 40_000, 'cols' => 8, 'shape' => 'strings', 'inline' => false,
                'title' => 'Только текст, shared strings — 40 000 × 8',
                'about' => 'Повторяющийся текст вынесен в sharedStrings.xml, как это делает Excel.',
            ],
            'strings-40k-inline' => [
                'rows' => 40_000, 'cols' => 8, 'shape' => 'strings', 'inline' => true,
                'title' => 'Только текст, inline strings — 40 000 × 8',
                'about' => 'Тот же контент без словаря строк — так пишут многие генераторы отчётов.',
            ],
            'numeric-40k' => [
                'rows' => 40_000, 'cols' => 10, 'shape' => 'numeric', 'inline' => false,
                'title' => 'Числа и даты — 40 000 × 10',
                'about' => 'Почти нет строк: проверяем стоимость конвертации чисел и дат.',
            ],
        ];
    }

    public function __construct(private readonly string $targetDir)
    {
        if (!is_dir($this->targetDir)) {
            mkdir($this->targetDir, 0o775, true);
        }
    }

    public function path(string $key): string
    {
        return $this->targetDir . '/' . $key . '.xlsx';
    }

    /**
     * @param \Closure(string, int, int): void|null $progress
     */
    public function generate(string $key, ?\Closure $progress = null): string
    {
        $spec = self::catalog()[$key] ?? throw new \InvalidArgumentException("Неизвестный набор: {$key}");
        $path = $this->path($key);

        $writer = new Writer(new Options(SHOULD_USE_INLINE_STRINGS: $spec['inline']));
        $writer->openToFile($path);

        $writer->addRow(Row::fromValues($this->header($spec['shape'], $spec['cols'])));

        mt_srand(crc32($key));
        for ($i = 1; $i <= $spec['rows']; ++$i) {
            $writer->addRow(Row::fromValues($this->dataRow($spec['shape'], $spec['cols'], $i)));
            if ($progress !== null && $i % 5_000 === 0) {
                $progress($key, $i, $spec['rows']);
            }
        }

        $writer->close();

        return $path;
    }

    /** @return list<string> */
    private function header(string $shape, int $cols): array
    {
        $base = match ($shape) {
            'strings' => ['Код', 'Клиент', 'Город', 'Статус', 'Менеджер', 'Комментарий', 'Источник', 'Тег'],
            'numeric' => ['ID', 'Дата', 'Количество', 'Цена', 'Скидка', 'Сумма', 'НДС', 'Вес', 'Объём', 'Курс'],
            default   => ['ID', 'Артикул', 'Клиент', 'Город', 'Дата заказа', 'Количество', 'Цена', 'Сумма', 'Статус', 'Комментарий'],
        };

        $header = [];
        for ($i = 0; $i < $cols; ++$i) {
            $header[] = $base[$i] ?? 'Колонка ' . ($i + 1);
        }

        return $header;
    }

    /** @return list<mixed> */
    private function dataRow(string $shape, int $cols, int $i): array
    {
        $row = match ($shape) {
            'strings' => $this->stringsRow($i),
            'numeric' => $this->numericRow($i),
            default   => $this->mixedRow($i),
        };

        // Добираем или обрезаем до нужной ширины
        for ($c = \count($row); $c < $cols; ++$c) {
            $row[] = $c % 3 === 0
                ? mt_rand(1, 999_999)
                : self::CITIES[$c % \count(self::CITIES)] . '-' . mt_rand(10, 99);
        }

        return \array_slice($row, 0, $cols);
    }

    /** @return list<mixed> */
    private function mixedRow(int $i): array
    {
        $qty = mt_rand(1, 250);
        $price = mt_rand(1_000, 9_999_99) / 100;

        return [
            $i,
            sprintf('ART-%06d', mt_rand(1, 99_999)),
            self::FIRST_NAMES[mt_rand(0, \count(self::FIRST_NAMES) - 1)] . ' '
                . self::LAST_NAMES[mt_rand(0, \count(self::LAST_NAMES) - 1)],
            self::CITIES[mt_rand(0, \count(self::CITIES) - 1)],
            (new \DateTimeImmutable('2024-01-01'))->modify('+' . mt_rand(0, 700) . ' days'),
            $qty,
            $price,
            round($qty * $price, 2),
            self::STATUSES[mt_rand(0, \count(self::STATUSES) - 1)],
            mt_rand(0, 4) === 0 ? '' : 'Комментарий №' . mt_rand(1, 5_000),
        ];
    }

    /** @return list<mixed> */
    private function stringsRow(int $i): array
    {
        return [
            sprintf('K-%08d', $i),
            self::FIRST_NAMES[mt_rand(0, \count(self::FIRST_NAMES) - 1)] . ' '
                . self::LAST_NAMES[mt_rand(0, \count(self::LAST_NAMES) - 1)],
            self::CITIES[mt_rand(0, \count(self::CITIES) - 1)],
            self::STATUSES[mt_rand(0, \count(self::STATUSES) - 1)],
            self::LAST_NAMES[mt_rand(0, \count(self::LAST_NAMES) - 1)],
            'Заметка длиной побольше, чтобы строка не помещалась в короткий буфер: ' . mt_rand(1, 20_000),
            ['сайт', 'телефон', 'партнёр', 'маркетплейс'][mt_rand(0, 3)],
            '#tag' . mt_rand(1, 200),
        ];
    }

    /** @return list<mixed> */
    private function numericRow(int $i): array
    {
        $qty = mt_rand(1, 5_000);
        $price = mt_rand(100, 500_000) / 100;
        $discount = mt_rand(0, 30) / 100;
        $sum = round($qty * $price * (1 - $discount), 2);

        return [
            $i,
            (new \DateTimeImmutable('2023-06-01'))->modify('+' . mt_rand(0, 900) . ' days'),
            $qty,
            $price,
            $discount,
            $sum,
            round($sum * 0.2, 2),
            mt_rand(1, 100_000) / 1_000,
            mt_rand(1, 50_000) / 10_000,
            mt_rand(60, 120) + mt_rand(0, 9_999) / 10_000,
        ];
    }
}
