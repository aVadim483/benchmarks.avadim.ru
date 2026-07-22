<?php

declare(strict_types=1);

namespace App\Bench;

enum Mode: string
{
    /** Последовательное чтение всех строк листа наиболее идиоматичным для библиотеки способом. */
    case ReadAll = 'read_all';

    /** Открыть файл и получить только первую строку — цена «входа» в файл. */
    case FirstRow = 'first_row';

    /** Получить весь лист одним массивом (кто умеет) — сценарий «данные целиком в память». */
    case ToArray = 'to_array';

    public function title(): string
    {
        return match ($this) {
            self::ReadAll  => 'Чтение всех строк',
            self::FirstRow => 'Открытие + первая строка',
            self::ToArray  => 'Весь лист в массив',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ReadAll => 'Основной сценарий: пройти по всем строкам первого листа и получить значения ячеек. '
                . 'Значения не накапливаются в памяти — там, где библиотека умеет отдавать строки потоком, используется поток.',
            self::FirstRow => 'Сколько стоит просто открыть файл и получить первую строку. Показывает, обязана ли '
                . 'библиотека разобрать весь документ до того, как отдаст первые данные.',
            self::ToArray => 'Весь лист загружается в один PHP-массив. Самый частый способ использования и самый '
                . 'тяжёлый по памяти.',
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return [self::ReadAll, self::FirstRow, self::ToArray];
    }
}
