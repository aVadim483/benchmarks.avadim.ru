<?php

declare(strict_types=1);

namespace App\Storage;

use App\Support\Config;

final class ResultStore
{
    private readonly string $dir;

    public function __construct()
    {
        $this->dir = Config::path('results');
    }

    /** @param array<string, mixed> $report */
    public function saveRun(array $report): string
    {
        $id = (string) $report['id'];
        if (preg_match('/^[a-f0-9]{16}$/', $id) !== 1) {
            throw new \InvalidArgumentException('Некорректный идентификатор прогона');
        }

        file_put_contents(
            $this->dir . '/run-' . $id . '.json',
            json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION),
        );

        return $id;
    }

    /** @return array<string, mixed>|null */
    public function loadRun(string $id): ?array
    {
        if (preg_match('/^[a-f0-9]{16}$/', $id) !== 1) {
            return null;
        }

        $file = $this->dir . '/run-' . $id . '.json';
        if (!is_file($file)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? $data : null;
    }

    /** @param array<string, mixed> $baseline */
    public function saveBaseline(array $baseline): void
    {
        file_put_contents(
            $this->dir . '/baseline.json',
            json_encode($baseline, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION),
        );
    }

    /** @return array<string, mixed>|null */
    public function loadBaseline(): ?array
    {
        $file = $this->dir . '/baseline.json';
        if (!is_file($file)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? $data : null;
    }

    /** Удалить прогоны и загруженные файлы старше указанного возраста. */
    public function purge(int $hours): int
    {
        $threshold = time() - $hours * 3600;
        $removed = 0;

        foreach (glob($this->dir . '/run-*.json') ?: [] as $file) {
            if (filemtime($file) < $threshold) {
                @unlink($file);
                ++$removed;
            }
        }
        foreach (glob(Config::path('uploads') . '/*') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < $threshold) {
                @unlink($file);
            }
        }
        foreach (glob(Config::path('tmp') . '/*') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < $threshold) {
                @unlink($file);
            }
        }

        return $removed;
    }
}
