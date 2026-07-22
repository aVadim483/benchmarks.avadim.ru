<?php

declare(strict_types=1);

namespace App\Bench\Adapter;

use App\Bench\Adapter;
use App\Bench\Tally;
use Shuchkin\SimpleXLSX;

final class SimpleXlsxAdapter extends Adapter
{
    public static function id(): string
    {
        return 'simplexlsx';
    }

    public static function label(): string
    {
        return 'SimpleXLSX';
    }

    public static function package(): string
    {
        return 'shuchkin/simplexlsx';
    }

    public static function homepage(): string
    {
        return 'https://github.com/shuchkin/simplexlsx';
    }

    public static function streaming(): bool
    {
        return false;
    }

    public static function note(): string
    {
        return 'Один класс без зависимостей. readRows() — генератор, но XML листа целиком лежит в SimpleXMLElement.';
    }

    private function open(string $file): SimpleXLSX
    {
        $xlsx = SimpleXLSX::parse($file);
        if ($xlsx === false) {
            throw new \RuntimeException('SimpleXLSX: ' . SimpleXLSX::parseError());
        }

        return $xlsx;
    }

    public function readAll(string $file): Tally
    {
        $xlsx = $this->open($file);

        $rows = $cells = $bytes = 0;
        foreach ($xlsx->readRows() as $row) {
            ++$rows;
            [$c, $b] = self::measureRow($row);
            $cells += $c;
            $bytes += $b;
        }

        return new Tally($rows, $cells, $bytes);
    }

    public function firstRow(string $file): Tally
    {
        $xlsx = $this->open($file);

        $rows = $cells = $bytes = 0;
        foreach ($xlsx->readRows(0, 1) as $row) {
            $rows = 1;
            [$cells, $bytes] = self::measureRow($row);
            break;
        }

        return new Tally($rows, $cells, $bytes);
    }

    public function toArray(string $file): ?Tally
    {
        $xlsx = $this->open($file);
        $data = $xlsx->rows();

        $cells = $bytes = 0;
        foreach ($data as $row) {
            [$c, $b] = self::measureRow($row);
            $cells += $c;
            $bytes += $b;
        }

        return new Tally(\count($data), $cells, $bytes);
    }
}
