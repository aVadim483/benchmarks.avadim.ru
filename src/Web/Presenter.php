<?php

declare(strict_types=1);

namespace App\Web;

use App\Bench\Registry;
use App\Support\Format;

/**
 * Превращает сырые замеры в готовые к выводу ряды: сортировка, кратности, длины баров.
 */
final class Presenter
{
    public const METRIC_TIME = 'time_ms';
    public const METRIC_MEMORY = 'peak_bytes';

    /**
     * @param array<string, array<string, mixed>> $modeResults [библиотека => замер]
     *
     * @return list<array{
     *     id:string, label:string, subject:bool, status:string, error:?string,
     *     value:?float, formatted:string, ratio:?float, ratio_label:?string,
     *     percent:float, best:bool, rows:?int, cells:?int
     * }>
     */
    public static function series(array $modeResults, string $metric): array
    {
        $labels = [];
        foreach (Registry::describe() as $lib) {
            $labels[$lib['id']] = $lib;
        }

        $rows = [];
        foreach ($modeResults as $id => $result) {
            $ok = ($result['status'] ?? '') === 'ok';
            $value = $ok ? (float) ($result[$metric] ?? 0) : null;

            $rows[] = [
                'id'        => $id,
                'label'     => $labels[$id]['label'] ?? $id,
                'subject'   => ($labels[$id]['subject'] ?? false) === true,
                'status'    => (string) ($result['status'] ?? 'error'),
                'error'     => $ok ? null : (string) ($result['error'] ?? ''),
                'value'     => $value,
                'formatted' => $ok
                    ? ($metric === self::METRIC_TIME
                        ? Format::ms((float) $result[$metric])
                        : Format::bytes((int) $result[$metric]))
                    : self::statusLabel((string) ($result['status'] ?? 'error')),
                'rows'      => $ok ? (int) ($result['rows'] ?? 0) : null,
                'cells'     => $ok ? (int) ($result['cells'] ?? 0) : null,
                'ratio'       => null,
                'ratio_label' => null,
                'percent'     => 0.0,
                'best'        => false,
            ];
        }

        $values = array_values(array_filter(
            array_column($rows, 'value'),
            static fn (?float $v): bool => $v !== null && $v > 0,
        ));

        $best = $values === [] ? null : min($values);
        $worst = $values === [] ? null : max($values);

        foreach ($rows as &$row) {
            if ($row['value'] === null || $best === null || $worst === null) {
                continue;
            }
            $row['ratio'] = $best > 0 ? $row['value'] / $best : 1.0;
            $row['ratio_label'] = $row['ratio'] < 1.005 ? 'быстрее всех' : Format::ratio($row['ratio']);
            $row['percent'] = $worst > 0 ? max(1.5, $row['value'] / $worst * 100) : 0.0;
            $row['best'] = abs($row['value'] - $best) < 1e-9;
        }
        unset($row);

        // Сначала успешные по возрастанию метрики, затем сбойные
        usort($rows, static function (array $a, array $b): int {
            if (($a['value'] === null) !== ($b['value'] === null)) {
                return $a['value'] === null ? 1 : -1;
            }

            return ($a['value'] ?? 0) <=> ($b['value'] ?? 0);
        });

        return $rows;
    }

    /**
     * Насколько исследуемая библиотека опережает остальных в данном сценарии.
     *
     * @param array<string, array<string, mixed>> $modeResults
     *
     * @return array{value:?float, best:bool, vs:list<array{label:string, ratio:float}>}
     */
    public static function subjectSummary(array $modeResults, string $metric): array
    {
        $series = self::series($modeResults, $metric);

        $subject = null;
        foreach ($series as $row) {
            if ($row['subject']) {
                $subject = $row;
                break;
            }
        }

        if ($subject === null || $subject['value'] === null || $subject['value'] <= 0) {
            return ['value' => null, 'best' => false, 'vs' => []];
        }

        $vs = [];
        foreach ($series as $row) {
            if ($row['subject'] || $row['value'] === null) {
                continue;
            }
            $vs[] = ['label' => $row['label'], 'ratio' => $row['value'] / $subject['value']];
        }

        return ['value' => $subject['value'], 'best' => $subject['best'], 'vs' => $vs];
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'ok'          => 'ок',
            'timeout'     => 'таймаут',
            'oom'         => 'не хватило памяти',
            'unsupported' => 'не поддерживается',
            'skipped'     => 'пропущено',
            'crash'       => 'процесс упал',
            default       => 'ошибка',
        };
    }
}
