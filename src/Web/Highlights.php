<?php

declare(strict_types=1);

namespace App\Web;

use App\Bench\Mode;
use App\Bench\Registry;
use App\Support\Format;

/**
 * Выжимка из эталонного прогона: несколько цифр для шапки и текстовые выводы.
 * Всё считается из данных — если исследуемая библиотека где-то проигрывает,
 * это так и будет написано.
 */
final class Highlights
{
    /**
     * @param array<string, mixed> $baseline
     *
     * @return array{
     *     headline: list<array{value:string, label:string}>,
     *     takeaways: list<string>,
     *     reference: ?array<string, mixed>
     * }
     */
    public static function build(array $baseline): array
    {
        $datasets = $baseline['datasets'] ?? [];
        if (!is_array($datasets) || $datasets === []) {
            return ['headline' => [], 'takeaways' => [], 'reference' => null];
        }

        // Опорный набор — самый большой по количеству ячеек
        $reference = null;
        $referenceCells = -1;
        foreach ($datasets as $dataset) {
            $cells = (int) ($dataset['file']['rows'] ?? 0) * (int) ($dataset['file']['cols'] ?? 0);
            if ($cells > $referenceCells) {
                $referenceCells = $cells;
                $reference = $dataset;
            }
        }

        $headline = [];
        $takeaways = [];

        if ($reference !== null) {
            $readAll = $reference['results'][Mode::ReadAll->value] ?? [];

            $time = Presenter::subjectSummary($readAll, Presenter::METRIC_TIME);
            $memory = Presenter::subjectSummary($readAll, Presenter::METRIC_MEMORY);

            if ($time['value'] !== null) {
                $headline[] = [
                    'value' => Format::ms($time['value']),
                    'label' => 'FastExcelReader читает ' . Format::number($referenceCells)
                        . ' ячеек (' . Format::bytes((int) $reference['file']['size']) . ')',
                ];

                $slowest = self::maxRatio($time['vs']);
                if ($slowest !== null) {
                    $headline[] = [
                        'value' => Format::ratio($slowest['ratio']),
                        'label' => 'быстрее, чем ' . $slowest['label'] . ', на том же файле',
                    ];
                }
            }

            if ($memory['value'] !== null) {
                $headline[] = [
                    'value' => Format::bytes((int) $memory['value']),
                    'label' => 'пик памяти на этом же чтении',
                ];

                $heaviest = self::maxRatio($memory['vs']);
                if ($heaviest !== null) {
                    $headline[] = [
                        'value' => Format::ratio($heaviest['ratio']),
                        'label' => 'меньше памяти, чем ' . $heaviest['label'],
                    ];
                }
            }
        }

        // Кто сколько раз оказался лучшим — по времени и по памяти
        $wins = ['time' => [], 'memory' => []];
        $total = 0;
        foreach ($datasets as $dataset) {
            foreach (Mode::all() as $mode) {
                $modeResults = $dataset['results'][$mode->value] ?? [];
                if ($modeResults === []) {
                    continue;
                }
                ++$total;
                foreach ([['time', Presenter::METRIC_TIME], ['memory', Presenter::METRIC_MEMORY]] as [$k, $metric]) {
                    foreach (Presenter::series($modeResults, $metric) as $row) {
                        $wins[$k][$row['label']] ??= 0;
                        if ($row['best']) {
                            ++$wins[$k][$row['label']];
                        }
                    }
                }
            }
        }

        arsort($wins['time']);
        arsort($wins['memory']);

        $subjectLabel = self::subjectLabel();

        $takeaways[] = sprintf(
            'Всего %d %s: %d %s × 3 сценария. Чаще всех оказывался самым быстрым %s, '
            . 'самым экономным по памяти — %s. У %s %d %s по скорости и %d по памяти.',
            $total,
            Format::plural($total, 'замер', 'замера', 'замеров'),
            \count($datasets),
            Format::plural(\count($datasets), 'набор', 'набора', 'наборов'),
            self::leaderText($wins['time']),
            self::leaderText($wins['memory']),
            $subjectLabel,
            $wins['time'][$subjectLabel] ?? 0,
            Format::plural($wins['time'][$subjectLabel] ?? 0, 'победа', 'победы', 'побед'),
            $wins['memory'][$subjectLabel] ?? 0,
        );

        $pairwise = self::pairwise($datasets);
        if ($pairwise !== []) {
            $takeaways[] = 'Попарно с ' . $subjectLabel . ' по всем замерам (медиана отношения): '
                . implode('; ', $pairwise) . '.';
        }

        $takeaways = array_merge($takeaways, self::structuralNotes($datasets));

        return ['headline' => $headline, 'takeaways' => $takeaways, 'reference' => $reference];
    }

    /**
     * Наблюдения, которые видно из самих чисел, а не из общих соображений.
     *
     * @param array<int, array<string, mixed>> $datasets
     *
     * @return list<string>
     */
    private static function structuralNotes(array $datasets): array
    {
        $notes = [];

        // Стриминговые библиотеки: память почти не зависит от размера файла
        $memoryByAdapter = [];
        $sizes = [];
        foreach ($datasets as $dataset) {
            $size = (int) ($dataset['file']['size'] ?? 0);
            $sizes[] = $size;
            foreach ($dataset['results'][Mode::ReadAll->value] ?? [] as $id => $result) {
                if (($result['status'] ?? '') === 'ok') {
                    $memoryByAdapter[$id][] = (int) $result['peak_bytes'];
                }
            }
        }

        $labels = array_column(Registry::describe(), 'label', 'id');

        $flat = [];
        $growing = [];
        foreach ($memoryByAdapter as $id => $values) {
            if (\count($values) < 3) {
                continue;
            }
            $ratio = min($values) > 0 ? max($values) / min($values) : 0;
            if ($ratio < 3) {
                $flat[] = $labels[$id] ?? $id;
            } elseif ($ratio > 15) {
                $growing[] = ($labels[$id] ?? $id) . ' (' . Format::ratio($ratio) . ')';
            }
        }

        if ($flat !== []) {
            $notes[] = 'Расход памяти почти не зависит от размера файла у: ' . implode(', ', $flat)
                . '. Такие библиотеки безопасно ставить туда, где размер входного файла заранее неизвестен.';
        }
        if ($growing !== []) {
            $notes[] = 'Пик памяти растёт вместе с файлом у: ' . implode(', ', $growing)
                . ' — разница между самым маленьким и самым большим набором.';
        }

        // Сравнение shared strings и inline strings
        $shared = self::findDataset($datasets, 'strings-40k-shared');
        $inline = self::findDataset($datasets, 'strings-40k-inline');
        if ($shared !== null && $inline !== null) {
            $timeDiffs = [];
            $memoryDiffs = [];
            foreach ($shared['results'][Mode::ReadAll->value] ?? [] as $id => $result) {
                $other = $inline['results'][Mode::ReadAll->value][$id] ?? null;
                if (($result['status'] ?? '') !== 'ok' || ($other['status'] ?? '') !== 'ok') {
                    continue;
                }
                $label = $labels[$id] ?? $id;

                $ratio = (float) $result['time_ms'] / max(0.001, (float) $other['time_ms']);
                if ($ratio > 1.15 || $ratio < 0.87) {
                    $timeDiffs[] = $label . ' — ' . ($ratio > 1
                        ? 'на ' . round(($ratio - 1) * 100) . '% медленнее'
                        : 'на ' . round((1 - $ratio) * 100) . '% быстрее');
                }

                $memoryRatio = (int) $result['peak_bytes'] / max(1, (int) $other['peak_bytes']);
                if ($memoryRatio > 1.5) {
                    $memoryDiffs[] = $label . ' — ' . Format::bytes((int) $other['peak_bytes'])
                        . ' против ' . Format::bytes((int) $result['peak_bytes'])
                        . ' (' . Format::ratio($memoryRatio) . ')';
                }
            }
            if ($timeDiffs !== []) {
                $notes[] = 'Один и тот же текст, записанный через словарь sharedStrings, читается не так,'
                    . ' как записанный инлайном. По времени на словаре: ' . implode('; ', $timeDiffs) . '.';
            }
            if ($memoryDiffs !== []) {
                $notes[] = 'Словарь sharedStrings дороже по памяти — библиотеки держат его целиком в ОЗУ.'
                    . ' Инлайн против словаря: ' . implode('; ', $memoryDiffs)
                    . '. Если вы сами генерируете файлы для последующего чтения, это заметный рычаг.';
            }
        }

        // Цена «открыть файл и прочитать первую строку»
        $largest = null;
        $largestSize = -1;
        foreach ($datasets as $dataset) {
            if ((int) ($dataset['file']['size'] ?? 0) > $largestSize) {
                $largestSize = (int) $dataset['file']['size'];
                $largest = $dataset;
            }
        }
        if ($largest !== null) {
            $parts = [];
            foreach ($largest['results'][Mode::FirstRow->value] ?? [] as $id => $result) {
                if (($result['status'] ?? '') === 'ok') {
                    $parts[] = ($labels[$id] ?? $id) . ' — ' . Format::ms((float) $result['time_ms']);
                }
            }
            if ($parts !== []) {
                $notes[] = 'Стоимость «открыть файл и получить первую строку» на самом большом наборе: '
                    . implode(', ', $parts) . '. Это важно для валидации заголовков перед импортом.';
            }
        }

        return $notes;
    }

    /**
     * @param array<int, array<string, mixed>> $datasets
     *
     * @return array<string, mixed>|null
     */
    private static function findDataset(array $datasets, string $key): ?array
    {
        foreach ($datasets as $dataset) {
            if (($dataset['key'] ?? null) === $key) {
                return $dataset;
            }
        }

        return null;
    }

    /**
     * @param list<array{label:string, ratio:float}> $vs
     *
     * @return array{label:string, ratio:float}|null
     */
    private static function maxRatio(array $vs): ?array
    {
        $best = null;
        foreach ($vs as $item) {
            if ($best === null || $item['ratio'] > $best['ratio']) {
                $best = $item;
            }
        }

        return $best !== null && $best['ratio'] > 1.05 ? $best : null;
    }

    /**
     * Медианное отношение «конкурент / предмет теста» по времени и по памяти
     * на всех замерах, где обе библиотеки отработали успешно.
     *
     * @param array<int, array<string, mixed>> $datasets
     *
     * @return list<string>
     */
    private static function pairwise(array $datasets): array
    {
        $subjectId = Registry::SUBJECT;
        $labels = array_column(Registry::describe(), 'label', 'id');

        /** @var array<string, array{time:list<float>, memory:list<float>}> $ratios */
        $ratios = [];

        foreach ($datasets as $dataset) {
            foreach (Mode::all() as $mode) {
                $modeResults = $dataset['results'][$mode->value] ?? [];
                $subject = $modeResults[$subjectId] ?? null;
                if (($subject['status'] ?? '') !== 'ok') {
                    continue;
                }

                foreach ($modeResults as $id => $result) {
                    if ($id === $subjectId || ($result['status'] ?? '') !== 'ok') {
                        continue;
                    }
                    $ratios[$id]['time'][] = (float) $result['time_ms'] / max(0.001, (float) $subject['time_ms']);
                    $ratios[$id]['memory'][] = (int) $result['peak_bytes'] / max(1, (int) $subject['peak_bytes']);
                }
            }
        }

        $out = [];
        foreach ($ratios as $id => $lists) {
            if (\count($lists['time']) < 3) {
                continue;
            }
            $time = self::medianOf($lists['time']);
            $memory = self::medianOf($lists['memory']);

            $out[] = sprintf(
                '%s — по времени %s, по памяти %s',
                $labels[$id] ?? $id,
                $time >= 1
                    ? 'медленнее в ' . trim(Format::ratio($time), '×') . ' раза'
                    : 'быстрее в ' . trim(Format::ratio(1 / $time), '×') . ' раза',
                $memory >= 1
                    ? 'тяжелее в ' . trim(Format::ratio($memory), '×') . ' раза'
                    : 'легче в ' . trim(Format::ratio(1 / $memory), '×') . ' раза',
            );
        }

        return $out;
    }

    /** @param list<float> $values */
    private static function medianOf(array $values): float
    {
        sort($values);
        $count = \count($values);
        $middle = intdiv($count, 2);

        return $count % 2 === 1
            ? $values[$middle]
            : ($values[$middle - 1] + $values[$middle]) / 2;
    }

    /** @param array<string, int> $wins Победы по убыванию */
    private static function leaderText(array $wins): string
    {
        $top = null;
        $count = 0;
        foreach ($wins as $label => $number) {
            if ($top === null) {
                $top = $label;
                $count = $number;
            }
        }

        return $top === null ? '—' : $top . ' (' . $count . ')';
    }

    public static function subjectLabel(): string
    {
        foreach (Registry::describe() as $lib) {
            if ($lib['subject']) {
                return $lib['label'];
            }
        }

        return 'FastExcelReader';
    }
}
