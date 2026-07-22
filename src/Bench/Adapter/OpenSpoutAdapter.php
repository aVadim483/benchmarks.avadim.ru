<?php

declare(strict_types=1);

namespace App\Bench\Adapter;

use App\Bench\Adapter;
use App\Bench\Tally;
use OpenSpout\Reader\XLSX\Reader;

final class OpenSpoutAdapter extends Adapter
{
    public static function id(): string
    {
        return 'openspout';
    }

    public static function label(): string
    {
        return 'OpenSpout';
    }

    public static function package(): string
    {
        return 'openspout/openspout';
    }

    public static function homepage(): string
    {
        return 'https://github.com/openspout/openspout';
    }

    public static function streaming(): bool
    {
        return true;
    }

    public static function note(): string
    {
        return 'Наследник box/spout. Потоковые итераторы; shared strings кэшируются на диск при нехватке памяти.';
    }

    public function readAll(string $file): Tally
    {
        $reader = new Reader();
        $reader->open($file);

        $rows = $cells = $bytes = 0;
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                ++$rows;
                [$c, $b] = self::measureRow($row->toArray());
                $cells += $c;
                $bytes += $b;
            }
            break; // только первый лист
        }
        $reader->close();

        return new Tally($rows, $cells, $bytes);
    }

    public function firstRow(string $file): Tally
    {
        $reader = new Reader();
        $reader->open($file);

        $cells = $bytes = 0;
        $rows = 0;
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows = 1;
                [$cells, $bytes] = self::measureRow($row->toArray());
                break;
            }
            break;
        }
        $reader->close();

        return new Tally($rows, $cells, $bytes);
    }

    public function toArray(string $file): ?Tally
    {
        $reader = new Reader();
        $reader->open($file);

        $data = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $data[] = $row->toArray();
            }
            break;
        }
        $reader->close();

        $cells = $bytes = 0;
        foreach ($data as $row) {
            [$c, $b] = self::measureRow($row);
            $cells += $c;
            $bytes += $b;
        }

        return new Tally(\count($data), $cells, $bytes);
    }
}
