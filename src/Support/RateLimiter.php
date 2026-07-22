<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Простой файловый лимитер: N событий на ключ в скользящем окне.
 * Регистрация пользователям не нужна, поэтому ключ — хэш IP.
 */
final class RateLimiter
{
    private readonly string $dir;

    public function __construct(
        private readonly int $max,
        private readonly int $window,
    ) {
        $this->dir = Config::path('tmp') . '/rate';
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0o775, true);
        }
    }

    /** @return array{allowed:bool, remaining:int, retry_after:int} */
    public function hit(string $key): array
    {
        $file = $this->dir . '/' . hash('xxh128', $key) . '.json';
        $now = time();

        $handle = fopen($file, 'c+');
        if ($handle === false) {
            return ['allowed' => true, 'remaining' => $this->max, 'retry_after' => 0];
        }

        flock($handle, LOCK_EX);
        $raw = (string) stream_get_contents($handle);
        $hits = json_decode($raw, true);
        $hits = is_array($hits) ? $hits : [];

        $since = $now - $this->window;
        $hits = array_values(array_filter(
            $hits,
            static fn (mixed $t): bool => is_int($t) && $t > $since,
        ));

        $allowed = \count($hits) < $this->max;
        if ($allowed) {
            $hits[] = $now;
        }

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, (string) json_encode($hits));
        flock($handle, LOCK_UN);
        fclose($handle);

        $retryAfter = $allowed || $hits === [] ? 0 : max(0, $hits[0] + $this->window - $now);

        return [
            'allowed'     => $allowed,
            'remaining'   => max(0, $this->max - \count($hits)),
            'retry_after' => $retryAfter,
        ];
    }

    public static function clientKey(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';

        return (string) $ip;
    }
}
