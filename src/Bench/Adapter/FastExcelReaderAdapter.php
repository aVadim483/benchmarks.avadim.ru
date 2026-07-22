<?php

declare(strict_types=1);

namespace App\Bench\Adapter;

use App\Bench\Adapter;
use App\Bench\Tally;
use avadim\FastExcelReader\Excel;

final class FastExcelReaderAdapter extends Adapter
{
    public static function id(): string
    {
        return 'fast-excel-reader';
    }

    public static function label(): string
    {
        return 'FastExcelReader';
    }

    public static function package(): string
    {
        return 'avadim/fast-excel-reader';
    }

    public static function homepage(): string
    {
        return 'https://github.com/aVadim483/fast-excel-reader';
    }

    public static function streaming(): bool
    {
        return true;
    }

    public static function note(): string
    {
        return 'Потоковый XMLReader поверх ZIP; строки отдаются генератором nextRow().';
    }

    public function readAll(string $file): Tally
    {
        $excel = Excel::open($file);
        $sheet = $excel->getFirstSheet();

        $rows = $cells = $bytes = 0;
        foreach ($sheet->nextRow([], Excel::KEYS_ZERO_BASED) as $row) {
            ++$rows;
            [$c, $b] = self::measureRow($row);
            $cells += $c;
            $bytes += $b;
        }

        return new Tally($rows, $cells, $bytes);
    }

    public function firstRow(string $file): Tally
    {
        $excel = Excel::open($file);
        $row = $excel->getFirstSheet()->readFirstRow();

        [$cells, $bytes] = self::measureRow($row);

        return new Tally($row === [] ? 0 : 1, $cells, $bytes);
    }

    public function toArray(string $file): ?Tally
    {
        $excel = Excel::open($file);
        $data = $excel->getFirstSheet()->readRows([], Excel::KEYS_ZERO_BASED);

        $cells = $bytes = 0;
        foreach ($data as $row) {
            [$c, $b] = self::measureRow($row);
            $cells += $c;
            $bytes += $b;
        }

        return new Tally(\count($data), $cells, $bytes);
    }
}
