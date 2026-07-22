<?php

declare(strict_types=1);

namespace App\Bench\Adapter;

use App\Bench\Adapter;
use App\Bench\Tally;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;

final class PhpSpreadsheetAdapter extends Adapter
{
    public static function id(): string
    {
        return 'phpspreadsheet';
    }

    public static function label(): string
    {
        return 'PhpSpreadsheet';
    }

    public static function package(): string
    {
        return 'phpoffice/phpspreadsheet';
    }

    public static function homepage(): string
    {
        return 'https://github.com/PHPOffice/PhpSpreadsheet';
    }

    public static function streaming(): bool
    {
        return false;
    }

    public static function note(): string
    {
        return 'Строит полную объектную модель книги. Замеряется в режиме setReadDataOnly(true) — без стилей и формул.';
    }

    private function reader(?IReadFilter $filter = null): XlsxReader
    {
        $reader = new XlsxReader();
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        if ($filter !== null) {
            $reader->setReadFilter($filter);
        }

        return $reader;
    }

    public function readAll(string $file): Tally
    {
        $spreadsheet = $this->reader()->load($file);
        $sheet = $spreadsheet->getSheet(0);

        $rows = $cells = $bytes = 0;
        foreach ($sheet->getRowIterator() as $row) {
            ++$rows;
            $iterator = $row->getCellIterator();
            $iterator->setIterateOnlyExistingCells(true);
            foreach ($iterator as $cell) {
                [$c, $b] = self::measureRow([$cell->getValue()]);
                $cells += $c;
                $bytes += $b;
            }
        }
        $spreadsheet->disconnectWorksheets();

        return new Tally($rows, $cells, $bytes);
    }

    public function firstRow(string $file): Tally
    {
        // Штатный способ ограничить объём чтения в PhpSpreadsheet — фильтр строк.
        $filter = new class implements IReadFilter {
            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $row === 1;
            }
        };

        $spreadsheet = $this->reader($filter)->load($file);
        $sheet = $spreadsheet->getSheet(0);

        $values = [];
        foreach ($sheet->getRowIterator(1, 1) as $row) {
            $iterator = $row->getCellIterator();
            $iterator->setIterateOnlyExistingCells(true);
            foreach ($iterator as $cell) {
                $values[] = $cell->getValue();
            }
        }
        $spreadsheet->disconnectWorksheets();

        [$cells, $bytes] = self::measureRow($values);

        return new Tally($values === [] ? 0 : 1, $cells, $bytes);
    }

    public function toArray(string $file): ?Tally
    {
        $spreadsheet = $this->reader()->load($file);
        $data = $spreadsheet->getSheet(0)->toArray(null, false, false, false);
        $spreadsheet->disconnectWorksheets();

        $cells = $bytes = 0;
        foreach ($data as $row) {
            [$c, $b] = self::measureRow($row);
            $cells += $c;
            $bytes += $b;
        }

        return new Tally(\count($data), $cells, $bytes);
    }
}
