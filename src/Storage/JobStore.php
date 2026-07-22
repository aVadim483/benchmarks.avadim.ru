<?php

declare(strict_types=1);

namespace App\Storage;

use App\Support\Config;

/**
 * Состояние пользовательского прогона. Прогон выполняется фоновым процессом,
 * страница результатов опрашивает этот файл — поэтому большой файл не упирается
 * в таймаут веб-сервера.
 */
final class JobStore
{
    private readonly string $dir;

    public function __construct()
    {
        $this->dir = Config::path('results');
    }

    public static function isValidId(string $id): bool
    {
        return preg_match('/^[a-f0-9]{16}$/', $id) === 1;
    }

    public static function newId(): string
    {
        return bin2hex(random_bytes(8));
    }

    /** @param array<string, mixed> $job */
    public function save(array $job): void
    {
        $id = (string) ($job['id'] ?? '');
        if (!self::isValidId($id)) {
            throw new \InvalidArgumentException('Некорректный идентификатор прогона');
        }

        $path = $this->path($id);
        $tmp = $path . '.' . getmypid() . '.tmp';
        file_put_contents(
            $tmp,
            json_encode($job, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION),
        );
        rename($tmp, $path);
    }

    /** @return array<string, mixed>|null */
    public function load(string $id): ?array
    {
        if (!self::isValidId($id)) {
            return null;
        }
        $path = $this->path($id);
        if (!is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : null;
    }

    public function path(string $id): string
    {
        return $this->dir . '/run-' . $id . '.json';
    }
}
