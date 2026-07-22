<?php

declare(strict_types=1);

namespace App\Bench;

use App\Support\Environment;

final class Benchmark
{
    /** @var null|\Closure(string, string, int, int): void */
    private ?\Closure $progress = null;

    public function __construct(
        private readonly int $repeats,
        private readonly ProcessRunner $runner,
    ) {
    }

    /** @param \Closure(string, string, int, int): void $callback */
    public function onProgress(\Closure $callback): self
    {
        $this->progress = $callback;

        return $this;
    }

    /**
     * Прогнать все библиотеки по всем сценариям для одного файла.
     *
     * @param list<Mode>|null  $modes
     * @param list<string>|null $adapterIds
     *
     * @return array<string, array<string, array<string, mixed>>> [сценарий][библиотека]
     */
    public function measureFile(string $file, ?array $modes = null, ?array $adapterIds = null): array
    {
        $modes ??= Mode::all();
        $adapterIds ??= Registry::ids();

        $results = [];
        foreach ($modes as $mode) {
            foreach ($adapterIds as $adapterId) {
                if ($this->progress !== null) {
                    ($this->progress)($mode->value, $adapterId, 0, $this->repeats);
                }
                $results[$mode->value][$adapterId] = $this->measureOne($adapterId, $mode, $file);
            }
        }

        return $results;
    }

    /** @return array<string, mixed> */
    private function measureOne(string $adapterId, Mode $mode, string $file): array
    {
        $samples = [];
        $failure = null;
        $planned = $this->repeats;

        for ($i = 1; $i <= $planned; ++$i) {
            $raw = $this->runner->run($adapterId, $mode, $file);

            if (($raw['ok'] ?? false) !== true) {
                $failure = $raw;
                break;
            }
            $samples[] = $raw;

            // Медленные замеры нет смысла повторять пять раз: разброс на таких
            // длительностях мал, а суммарное время прогона растёт линейно.
            if ($i === 1) {
                $first = (float) $raw['time_ms'];
                $planned = match (true) {
                    $first > 5_000 => min($planned, 1),
                    $first > 1_500 => min($planned, 2),
                    $first > 400   => min($planned, 3),
                    default        => $planned,
                };
            }

            if ($this->progress !== null) {
                ($this->progress)($mode->value, $adapterId, $i, $planned);
            }
        }

        if ($samples === []) {
            return [
                'status' => (string) ($failure['status'] ?? 'error'),
                'error'  => (string) ($failure['error'] ?? 'Неизвестная ошибка'),
            ];
        }

        $times = array_map(static fn (array $s): float => (float) $s['time_ms'], $samples);
        sort($times);

        $last = $samples[\count($samples) - 1];
        $peaks = array_map(static fn (array $s): int => (int) $s['peak_bytes'], $samples);

        // Память берётся по худшему повтору, а не по медиане: пик — это граница,
        // за которую процесс не выходил, и занижать её усреднением нельзя.
        $rssPeaks = self::ints(array_column($samples, 'rss_peak'));
        $rssDeltas = self::ints(array_column($samples, 'rss_delta'));
        $rssAvailable = $rssPeaks !== [] && $rssDeltas !== [];

        return [
            'status'      => 'ok',
            'time_ms'     => self::median($times),
            'time_min'    => $times[0],
            'time_max'    => $times[\count($times) - 1],
            'samples'     => array_map(static fn (float $t): float => round($t, 2), $times),
            // Основная метрика памяти: прирост RSS процесса за время замера.
            // Если платформа пик RSS не отдаёт — откатываемся на счётчик PHP,
            // и memory_source честно об этом говорит.
            'memory_bytes'  => $rssAvailable ? max($rssDeltas) : max($peaks),
            'memory_source' => $rssAvailable ? 'rss' : 'php-heap',
            'rss_peak'      => $rssAvailable ? max($rssPeaks) : null,
            'rss_delta'     => $rssAvailable ? max($rssDeltas) : null,
            'rss_baseline'  => isset($last['rss_baseline']) ? (int) $last['rss_baseline'] : null,
            'peak_bytes'    => max($peaks),
            'peak_delta'    => (int) $last['peak_delta'],
            'peak_real'     => (int) $last['peak_real'],
            'baseline'      => (int) $last['baseline'],
            'rows'          => (int) $last['rows'],
            'cells'         => (int) $last['cells'],
            'bytes'         => (int) $last['bytes'],
        ];
    }

    /**
     * @param  list<mixed> $values
     * @return list<int>
     */
    private static function ints(array $values): array
    {
        $out = [];
        foreach ($values as $value) {
            if (is_int($value) && $value >= 0) {
                $out[] = $value;
            }
        }

        return $out;
    }

    /** @param list<float> $sorted */
    private static function median(array $sorted): float
    {
        $count = \count($sorted);
        $middle = intdiv($count, 2);

        $value = $count % 2 === 1
            ? $sorted[$middle]
            : ($sorted[$middle - 1] + $sorted[$middle]) / 2;

        return round($value, 2);
    }

    /**
     * Собрать полноценный отчёт по одному файлу.
     *
     * @return array<string, mixed>
     */
    public function report(string $file, string $displayName, string $source, array $settings): array
    {
        return [
            'id'          => bin2hex(random_bytes(8)),
            'created_at'  => (new \DateTimeImmutable())->format(\DATE_ATOM),
            'source'      => $source,
            'file'        => FileProbe::inspect($file, $displayName),
            'settings'    => $settings,
            'environment' => Environment::describe(),
            'libraries'   => Registry::describe(),
            'results'     => $this->measureFile($file),
        ];
    }
}
