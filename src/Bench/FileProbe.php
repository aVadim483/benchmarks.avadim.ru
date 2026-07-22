<?php

declare(strict_types=1);

namespace App\Bench;

/**
 * Быстрый разбор структуры XLSX средствами ext-zip, без участия сравниваемых библиотек.
 * Нужен, чтобы описать тестовый файл в отчёте нейтрально.
 */
final class FileProbe
{
    /**
     * @return array{
     *     name:string, size:int, unpacked:int, sheets:int, shared_strings:bool,
     *     shared_strings_size:int, dimension:?string, rows:?int, cols:?int, valid:bool, error:?string
     * }
     */
    public static function inspect(string $file, ?string $displayName = null): array
    {
        $info = [
            'name'                => $displayName ?? basename($file),
            'size'                => is_file($file) ? (int) filesize($file) : 0,
            'unpacked'            => 0,
            'sheets'              => 0,
            'shared_strings'      => false,
            'shared_strings_size' => 0,
            'dimension'           => null,
            'rows'                => null,
            'cols'                => null,
            'valid'               => false,
            'error'               => null,
        ];

        $zip = new \ZipArchive();
        if ($zip->open($file) !== true) {
            $info['error'] = 'Файл не является корректным XLSX (не открывается как ZIP)';

            return $info;
        }

        $firstSheet = null;
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                continue;
            }
            $name = $stat['name'];
            $info['unpacked'] += (int) $stat['size'];

            if (preg_match('~^xl/worksheets/sheet\d+\.xml$~', $name) === 1) {
                ++$info['sheets'];
                if ($firstSheet === null || strnatcmp($name, $firstSheet) < 0) {
                    $firstSheet = $name;
                }
            }
            if ($name === 'xl/sharedStrings.xml') {
                $info['shared_strings'] = true;
                $info['shared_strings_size'] = (int) $stat['size'];
            }
        }

        if ($info['sheets'] === 0) {
            $info['error'] = 'В файле не найдено ни одного листа';
            $zip->close();

            return $info;
        }

        if ($firstSheet !== null) {
            $handle = $zip->getStream($firstSheet);
            if ($handle !== false) {
                $head = (string) fread($handle, 8192);
                fclose($handle);
                if (preg_match('~<dimension\s+ref="([^"]+)"~', $head, $m) === 1) {
                    $info['dimension'] = $m[1];
                    [$info['cols'], $info['rows']] = self::parseDimension($m[1]);
                }
            }
        }

        $zip->close();
        $info['valid'] = true;

        return $info;
    }

    /** @return array{0:?int, 1:?int} [колонок, строк] */
    private static function parseDimension(string $ref): array
    {
        $parts = explode(':', $ref);
        $last = $parts[\count($parts) - 1];
        if (preg_match('~^([A-Z]+)(\d+)$~', $last, $m) !== 1) {
            return [null, null];
        }

        $cols = 0;
        foreach (str_split($m[1]) as $letter) {
            $cols = $cols * 26 + (\ord($letter) - 64);
        }

        return [$cols, (int) $m[2]];
    }
}
