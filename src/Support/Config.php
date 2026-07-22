<?php

declare(strict_types=1);

namespace App\Support;

final class Config
{
    private static ?self $instance = null;

    /** @param array<string, mixed> $items */
    private function __construct(private readonly array $items)
    {
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            $base = require dirname(__DIR__, 2) . '/config/config.php';
            $localFile = dirname(__DIR__, 2) . '/config/config.local.php';
            if (is_file($localFile)) {
                $base = self::merge($base, require $localFile);
            }
            self::$instance = new self($base);
        }

        return self::$instance;
    }

    /** Доступ по «точечному» пути: Config::get('web.timeout') */
    public static function get(string $path, mixed $default = null): mixed
    {
        $value = self::instance()->items;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public static function path(string $name): string
    {
        $path = self::get('paths.' . $name);
        if (!is_string($path)) {
            throw new \InvalidArgumentException("Неизвестный путь: {$name}");
        }
        if (!is_dir($path)) {
            mkdir($path, 0o775, true);
        }

        return $path;
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private static function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key]) && !array_is_list($value)) {
                $base[$key] = self::merge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
