<?php

declare(strict_types=1);

namespace App\Bench;

/**
 * Результат «полезной работы» адаптера: сколько строк и ячеек он реально отдал.
 * Нужен, чтобы (а) движок не оптимизировал чтение вхолостую и (б) можно было
 * убедиться, что библиотеки прочитали сопоставимый объём данных.
 */
final readonly class Tally
{
    public function __construct(
        public int $rows = 0,
        public int $cells = 0,
        /** Сумма длин строковых представлений непустых значений. */
        public int $bytes = 0,
    ) {
    }

    /** @return array{rows:int, cells:int, bytes:int} */
    public function toArray(): array
    {
        return ['rows' => $this->rows, 'cells' => $this->cells, 'bytes' => $this->bytes];
    }
}
