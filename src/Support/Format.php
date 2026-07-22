<?php

declare(strict_types=1);

namespace App\Support;

final class Format
{
    public static function bytes(int $bytes, int $precision = 1): string
    {
        if ($bytes < 1024) {
            return $bytes . ' Б';
        }

        $units = ['КБ', 'МБ', 'ГБ', 'ТБ'];
        $value = $bytes / 1024;
        $unit = 0;
        while ($value >= 1024 && $unit < \count($units) - 1) {
            $value /= 1024;
            ++$unit;
        }

        return number_format($value, $value >= 100 ? 0 : $precision, ',', ' ') . ' ' . $units[$unit];
    }

    public static function ms(float $ms, int $precision = 0): string
    {
        if ($ms < 1) {
            return number_format($ms, 2, ',', ' ') . ' мс';
        }
        if ($ms < 1000) {
            return number_format($ms, $precision, ',', ' ') . ' мс';
        }

        return number_format($ms / 1000, 2, ',', ' ') . ' с';
    }

    public static function number(int|float $value): string
    {
        return number_format((float) $value, 0, ',', ' ');
    }

    /** Кратность вида «×3,4» */
    public static function ratio(float $value): string
    {
        if ($value >= 100) {
            return '×' . number_format($value, 0, ',', ' ');
        }

        return '×' . number_format($value, $value >= 10 ? 1 : 2, ',', ' ');
    }

    /** Склонение существительного по числу: plural(3, 'строка', 'строки', 'строк') */
    public static function plural(int $n, string $one, string $few, string $many): string
    {
        $mod100 = $n % 100;
        if ($mod100 >= 11 && $mod100 <= 14) {
            return $many;
        }

        return match ($n % 10) {
            1       => $one,
            2, 3, 4 => $few,
            default => $many,
        };
    }
}
